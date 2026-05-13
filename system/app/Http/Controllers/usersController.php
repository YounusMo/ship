<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class usersController extends Controller
{
    public function load(Request $request){
        if (!in_array(auth()->user()->type , ['admin'])) {
            abort(403, 'Unauthorized');
        }
        
        try {

            $get = DB::table('users');
            $get = $get->orderBy('id','DESC');
         
            if($request->search){
                $columns = Schema::getColumnListing('users');
                $except = ['id', 'created_by', 'created_time', 'created_date'];
                $columns_ = array_diff($columns, $except);
                $search = $this->escapeLike($request->search);

                $get = $get->where(function($q) use ($columns_, $search) {
                    foreach ($columns_ as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }


            $show_deleted = $request->showDeleted;
            $get = $get->where('deleted','false');

            $get = $get->paginate(env('PAGEVIEW'));
           
            
            return view('pages.users.table',compact('get','show_deleted'));

            
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), [
                'exception' => $th,
            ]);
        }
    }

    function removeLeadingZero($number) {
        // Type cast to an integer to remove any leading zeros
        $number = (int)$number;
        // Convert it back to a string if you need it as a string
        return (string)$number;
    }

    public function create(Request $request){
        if (!in_array(auth()->user()->type , ['admin'])) {
            abort(403, 'Unauthorized');
        }
        $response = null;

        DB::transaction(function () use ($request, &$response) {
            try {
                $pass = $request->pass1;
                $code  = $request->code;
                $email = $request->email;
                $name = $request->name;
                $type = $request->type;
                $branch = $request->branch;

                $chk  = DB::table('users')->where('not_active','false')->where('code',$code)->first();
                $chk2 = DB::table('clients')->where('not_active','false')->where('code',$code)->first();
                
                if($chk || $chk2){
                    $response = response()->json('exist', 200);
                    return;
                }
                $data = [];

                $data['code'] = $code;
                $data['password'] = Hash::make($pass);
                $data['created_date'] = date('Y-m-d');
                $data['created_time'] = date('H:i:s');
                $data['deleted'] = 'false';
                $data['not_active'] = 'false';
                $data['lang'] = 'en';
                $data['branch'] = $branch;
                $data['type'] = $type;
                $data['name'] = $name;

                $id = DB::table('users')->insertGetId($data);

                $response = response()->json(['status' => 'success', 'id' => $id], 200);
            } catch (\Throwable $th) {
                Log::error('Insert failed: ' . $th->getMessage(), ['exception' => $th]);
                $response = response()->json(['error' => 'Server error.'], 500);
            }
        });

        return $response;
    }

    public function delete(Request $request){
        if (!in_array(auth()->user()->type, ['admin'], true)) {
            abort(403, 'Unauthorized');
        }

        DB::transaction(function() use($request){
            try {

                $ids = json_decode($request->ids , true);

                if(count($ids) > 0){
                    DB::table('users')->whereIn('id',$ids)->update(['deleted'=>'true' , 'not_active'=>'true']);

                    foreach ($ids as $uid) {
                        $this->logAudit(
                            'user_delete',
                            'users',
                            $uid,
                            null,
                            'User account soft-deleted'
                        );
                    }
                }

            } catch (\Throwable $th) {
                Log::error($th->getMessage(), [
                    'exception' => $th,
                ]);
            }
        });
    }

    public function login(Request $request){
        $identifier = strtolower(trim($request->email));
        $password   = $request->password;

        // جرّب أولاً على جدول users
        $user = User::where('email', $identifier)
            ->orWhere('code', $identifier)
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            Auth::guard('web')->login($user);
            return redirect('/'); // أو أي صفحة dashboard خاصة بالـ users
        }

        // إذا ما لقيت، جرّب على جدول clients
        $client = Client::where('deleted','false')
            ->where(function($q)use($identifier){
                $q->where('email', $identifier)->orWhere('code', $identifier);
            })
            
            ->first();

        if ($client && Hash::check($password, $client->password)) {
            Auth::guard('client')->login($client);

            return redirect('/client'); // صفحة خاصة بالـ clients
        }

        // لو فشل الاثنين
        return redirect('/login')->with('err', 'Invalid credentials');
       
    }

    public function get(Request $request){
        $get = DB::table('users')->where('id',$request->id)->first();
        $br_name = '';
        if($get->type === 'branch_admin'){
            $branch = DB::table('branches')->where('id',$get->branch)->first();
            switch(auth()->user()->lang){
                case 'ar':
                    $br_name = $branch->name;
                break;
                case 'en':
                    $br_name = $branch->name_en;
                break;
                case 'zh':
                    $br_name = $branch->name_zh;
                break;
            }
        }
        return response()->json([$get,$br_name]);
    }
     

    public function change_pass(Request $request){
        if (!in_array(auth()->user()->type, ['admin'], true)) {
            abort(403, 'Unauthorized');
        }

        DB::table('users')->where('id',$request->id)->update([
            'password' => Hash::make($request->password),
        ]);

        // Never log the new password — only that one was set.
        $this->logAudit(
            'password_change',
            'users',
            $request->id,
            null,
            'Admin reset user password'
        );
    }

    public function save(Request $request){
        if (!in_array(auth()->user()->type, ['admin'], true)) {
            abort(403, 'Unauthorized');
        }

       $exists = DB::table('users')
        ->where(function($query) use ($request) {
            $query->Where('code', $request->code);
        })->where('not_active','false')
        ->where('id', '!=', $request->id)
        ->exists();

       $exists2 = DB::table('clients')
        ->where(function($query) use ($request) {
            $query->Where('code', $request->code);
        })->where('not_active','false')
        ->exists();

        if (!$exists && !$exists2) {
            $before = DB::table('users')->where('id', $request->id)
                ->first(['name','email','branch','type']);

            DB::table('users')
                ->where('id', $request->id)
                ->update([
                    'name'   => $request->name,
                    'email'  => $request->email,
                    'branch' => $request->branch,
                    'type'   => $request->type,
                ]);

            $this->logAudit(
                'user_update',
                'users',
                $request->id,
                [
                    'before' => $before ? (array) $before : null,
                    'after'  => [
                        'name'   => $request->name,
                        'email'  => $request->email,
                        'branch' => $request->branch,
                        'type'   => $request->type,
                    ],
                ],
                'User account updated'
            );
        } else {
            return response()->json('exist');
        }
    }


    public function logout(){
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        } elseif (Auth::guard('client')->check()) {
            Auth::guard('client')->logout();
        }
        return redirect('/login');
    }

    public function change_lang(Request $request){
        $lang = $request->lang;
        if (!in_array($lang, \App\Http\Controllers\langController::ALLOWED_LANGS, true)) {
            abort(422, 'Invalid language');
        }

        if (Auth::guard('web')->check()) {

            DB::table('users')->where('id',auth()->user()->id)->update([
                'lang' => $lang
            ]);
            return redirect('/');
        }

        if (Auth::guard('client')->check()) {

            DB::table('clients')->where('id',Auth::guard('client')->user()->id)->update([
                'lang' => $lang
            ]);

            return redirect('/client');
        }

        return redirect('/login');
        
    }


    public function save_profile(Request $request){
        $response = null;

        DB::transaction(function () use ($request, &$response) {
            try {

                $id = (int) auth()->user()->id;


                $names  = json_decode($request->input('names'), true);
                $values = json_decode($request->input('values'), true);

                // Profile-editable columns only. Crucially this prevents the
                // client from setting their own `type` (=> admin) or `branch`
                // via the names/values pair.
                $profileFields = ['name', 'email', 'phone'];

                $data = [];

                $errs = [];

                foreach ($names as $index => $name) {
                    if (!in_array($name, $profileFields, true)) {
                        continue;
                    }
                    $value = $values[$index] ?? null;
                    if (!empty($value)) {
                        if(in_array($name , ['email','phone'])){
                            $chk_exist = DB::table('users')->whereNot('id',$id)->where($name,$value)->count();
                            if($chk_exist > 0){
                                $errs[] = $name;
                            }
                        }

                        $data[$name] = $value;
                    }
                }

                if(count($errs) < 1){
                    DB::table('users')->where('id',$id)->update($data);

                    if ($request->hasFile('photo')) {
                        $folderName = 'photos/teacher/'.$id;
                        $storedName = $this->storeUploadedImage($request->file('photo'), $folderName);
                        if ($storedName !== null) {
                            DB::table('users')->where('id',$id)->update(['photo'=>$storedName]);
                        }
                    }
                }

                if(count($errs) > 0){
                    $response = response()->json(['status' => 'err' , 'errs'=>$errs], 200);
                }else{
                    $response = response()->json(['status' => 'success'], 200);
                }
                
            } catch (\Throwable $th) {
                Log::error('Insert failed: ' . $th->getMessage(), ['exception' => $th]);
                $response = response()->json(['error' => 'Server error.'], 500);
            }
        });

        return $response;
    }

}
