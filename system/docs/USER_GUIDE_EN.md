# ShipFlow User Guide

> Practical, step-by-step guide for every user type in the **ShipFlow / Mataz Trading** system.  
> The system has three parts: a **web admin site**, an **Employee mobile app** (Android / iOS), and a **Client mobile app** (Android / iOS) — plus a **web client portal**.

---

## User type index

| # | User | Where they work | What they do (in one line) |
|---|------|-----------------|----------------------------|
| 1 | **System Administrator** | Web admin | Everything: clients, shipping, accounting, branches, users, settings |
| 2 | **Accountant / Treasurer** | Web admin | Deposits, withdrawals, transfers, trial balance, reports, archive |
| 3 | **Operations (Shipping) Staff** | Web admin | Register air/sea shipments, move them between branches |
| 4 | **Branch Manager** (MANAGER) | Employee app | Scan, supervise the branch queue, dispatch staff |
| 5 | **Receiver** (RECEIVER) | Employee app | Receive goods at hub/branch and register them by QR |
| 6 | **Courier** (COURIER) | Employee app | Deliver shipments to clients |
| 7 | **Auditor** (AUDITOR) | Employee app | Audit inventory and stickers (read-only on sensitive events) |
| 8 | **Client** | Client app + web portal | Track balance, transactions, and shipments |

---

## 1) System Administrator — Web admin

> Full access. The default account is created by the ops engineer. Never share the password.

### First-time login
1. Open the browser and go to the system URL (e.g. `https://app.example.com`).
2. Enter your **email** and **password**, then click **Sign in**.
3. Switch the language toggle at the top-right between **English** and **العربية** as needed.
4. The left sidebar gives you access to every section.

### Daily / recurring tasks
| Task | Path in the sidebar | Steps |
|------|---------------------|-------|
| Add a new client | **Clients** → **+ Create** | Fill name, code, branch, phone, save. |
| Add a branch | **Branches** → **+ Create** | English and Arabic name, code. |
| Add a shipping line (supplier) | **Shipping Lines** | Name, default currency, opening balance if any. |
| Add a customs broker | **Customs clearance** | Name, opening balance, currency. |
| Add a new system user | **Users** | Name, email, password, account type. |
| Edit general settings | **Settings** | Company name, enabled currencies, default rates. |

### Supervision and review
- **Audit log**: shows every change in the system (who did it, when, which record). Use it for periodic review or when you suspect a mistake.
- **Drift Detector** (under Accounting): catches any drift between stored balances and what the journal actually says (Trial Balance).
- **Reconciliation**: for monthly matching against bank and cash.

### Backups and monthly close
1. On the last day of the month, review the **Trial Balance**. DR must equal CR for every currency.
2. If it doesn't balance, open **Drift Detector** to find the source.
3. From **Accounting Periods**, close the month with **Close**.
4. Generate **Profit & Loss (PDF)** and **Balance Sheet (PDF)** and keep an offline copy.

> ⚠️ Warning: do NOT close the period before the trial balance is in balance — closing prevents editing prior entries.

---

## 2) Accountant / Treasurer — Web admin

### Login
- Same login screen. You land on the dashboard after sign-in.

### Daily operations

#### Cash deposit from a client
1. **Clients** → pick the client from the list (or search by name / code).
2. Click **Edit** next to the client.
3. From the transactions section, choose **Deposit**.
4. Enter amount, currency (USD / EUR / LYD / CNY), and purpose.
5. Save. It appears immediately in **Daily Journal**.

#### Withdrawal from client balance
1. Same flow as deposit, but choose **Withdraw**.
2. Enter amount, currency, and reason.
3. Save.

#### Transfer between currencies or between two clients
- **Clients** → pick the client → **Transfer** (between his own currencies) or **Transfer to client** (move from one client to another).
- Confirm the FX rate; the current rate comes from **FX Rate History**.

#### Record a petty-cash expense at a branch
1. **Treasury** → pick the branch.
2. Click **+ Branch expense**.
3. Enter amount, currency, category, and description.

### Weekly / monthly reports
| Report | When to use |
|--------|-------------|
| **Trial Balance** | Daily, to confirm journal balance. |
| **Daily Journal** | To review today's entries. |
| **Client Aging** | To see which clients have outstanding amounts. |
| **Supplier Aging** | To track what's due to shipping lines. |
| **Broker Aging** | To track payments to customs brokers. |
| **Cash Count** | To record physical cash count and settle any difference. |
| **Treasury by branch** | To see the current balance per branch and currency. |
| **FX Rate History** | To update official FX rates in the system. |
| **Prepayments** | To track advances paid to suppliers and brokers. |
| **Old Balance Archive** | To review historical opening balances. |

### Golden rules for the accountant
1. **Never hard-delete an entry**. Reverse it with an opposite entry instead — that preserves the audit trail.
2. **The FX rate at the moment of the movement is what gets recorded**. Make sure it's up-to-date before logging any currency transfer.
3. **Every withdrawal needs a clear description**. A generic "withdraw" makes later review painful.
4. **Open Trial Balance before leaving the office every day**. If it doesn't balance, find the gap immediately — don't defer.

---

## 3) Operations (Shipping) Staff — Web admin

> Responsible for registering air and sea shipments and linking them to clients.

### Registering an air freight shipment (received)
1. **Air Freight** in the sidebar.
2. Make sure the tab is on **Received**.
3. Click **+ Create**.
4. Fill in:
   - **Client code** (the name appears automatically).
   - **Company name** (if the client trades under one).
   - **Origin country** (e.g. China).
   - **Type**: piece / box / parcel.
   - **Category** (A1 / A2 / B …).
   - **Weight (kg)**, **CBM**, and **count**.
   - **Receipt**: with receipt / without receipt.
   - **Brand** and any notes.
5. Save. The shipment moves to **Inside** once it enters the warehouse, and to **Outside** once dispatched.

### Sea freight
- Same idea from **Sea Freight**. Differences:
  - Choose the **Shipping Line** and the **container number**.
  - Tracking events (via ShipsGo) can later be linked automatically.

### Cancellations and returns
- To cancel a wrongly-received shipment: from the row, click **Edit** → **Cancel**. It moves to **Canceled received**.
- To return a sea trip: use the **Trips** tab → **Canceled trips**.

### Tips
- **Verify the client code before saving**. A shipment under the wrong code creates two invoices to untangle later.
- If the shipment has multiple pieces with separate tracking codes, add them in the **Tracking codes** field one per line — that lets the client track each piece individually from the mobile app.

---

## 4) Branch Manager (MANAGER) — Employee app

> The branch manager uses the mobile app to oversee branch operations, but can also scan and register events when needed.

### First-time login
1. Install the **ShipFlow Employee** app from the link provided by the admin.
2. On first launch: enter the **API base URL** given to you by the admin (one-time only).
3. Enter your **email** and **password**, then tap **Sign in**.
4. Pick your **active branch** from the dropdown at the top — the role next to the branch name shows your assignment (MANAGER / RECEIVER / COURIER / AUDITOR).

> If you see **"Your account isn't linked to any branch. Contact admin."** the system admin hasn't assigned you to a branch yet.

### Daily duties of the branch manager
1. **Track the branch queue**: tap **Branch queue** from the home screen. You'll see every shipment currently in your branch.
2. **Review "My activity"**: see every action you took today.
3. **Watch for pending sync**: if the orange banner shows "N scans pending sync", tap **Sync now** to flush them.
4. **Dispatch staff** (receivers, couriers) inside the branch.

### When the internet drops
- The app works **offline**: every scan is saved locally in the **outbox**.
- When the connection returns, the app uploads them automatically — or tap **Sync now** manually.
- Do NOT close the app before the orange counter reaches zero.

---

## 5) Receiver (RECEIVER) — Employee app

> The frontline role for receiving goods at the hub or branch.

### Steps on shipment arrival
1. From the home screen tap **Scan QR sticker**.
2. Point the camera at the sticker. If light is poor, turn on the **Torch**.
3. **If the sticker is brand new** (never scanned before):
   - You see the **"New sticker — first scan"** screen.
   - **Enter the piece ID (required)** that you see on the package.
   - Pick the action: **Received at hub** or **Received at branch** (whichever applies).
   - Add notes if needed (e.g. "slightly wet" / "box opened").
   - Tap **Submit**.
4. **If the sticker is already linked to a shipment**:
   - The last event is shown (e.g. "In transit — internal transfer").
   - Pick the next valid action from the list.
   - Tap **Submit**.

### Available actions (depending on current state)
| Action | When to use it |
|--------|----------------|
| **Received at hub** | First time the goods arrive at the central hub. |
| **In transit (internal transfer)** | Sending the goods to another branch. You must pick **Destination branch**. |
| **Received at branch** | When goods arrive at the delivery branch. |
| **Ready for pickup** | When the shipment is staged for client pickup. |
| **Delivered to customer** | After hand-off (typically used by the courier). |
| **Returned to hub** | To send a shipment from a branch back to the hub. |
| **Mark as lost** | If the shipment can't be located. |
| **Mark as damaged** | If the goods are damaged. |

### Important rules
- **You cannot register events at a branch you're not assigned to**. If you try, you'll see **"You aren't assigned to this branch."**
- Some actions aren't valid from certain states. If you see **"This action isn't allowed from the current state"**, ask the branch manager to check the shipment.
- If the connection drops: keep scanning. The app stores everything and uploads when back online.

---

## 6) Courier (COURIER) — Employee app

### Daily flow
1. Sign in and confirm the **active branch**.
2. Tap **Branch queue** to see shipments ready for delivery.
3. Tap a shipment to see the client's address and phone.
4. On arrival at the client and handing over the goods:
   - Open **Scan QR sticker**.
   - Scan every sticker on every piece you delivered.
   - Choose **Delivered to customer**.
   - Add a note if helpful (e.g. "received by the client's brother").
   - Tap **Submit**.

### If delivery fails
- Coordinate with the hub on the right next step: retry later, return to hub, or mark as lost (only on management instruction).

---

## 7) Auditor (AUDITOR) — Employee app

> Narrower scope: can scan to read state and confirm presence, but cannot perform sensitive financial events.

### Duties
1. **Periodic audit**: scan every sticker in the branch and reconcile against the **Branch queue** in the app.
2. **Confirm state**: each scan shows the last event. Any mismatch against reality goes to the branch manager.
3. **Review "My activity"**: the auditor can see their own activity in full, but cannot modify events created by others.

### Messages you may see
- **Sticker not found**: not a ShipFlow sticker, or not registered yet. Have the branch manager register it.
- **Sticker revoked**: a sticker that was previously voided. Discard it — do not reuse it.

---

## 8) Client — Client app (Android / iOS) + web portal

> The client sees balance, transactions, shipments, and Mataz notifications from the app or the website.

### Sign-in (app)
1. Install the **Ship Flow** app from the store link provided by Mataz.
2. In the **Email or code** field: enter your client code (e.g. `101`) or your email address.
3. Enter the **password** given to you by the branch manager.
4. Tap **Sign in**.
5. On first launch the app will request notification permission — accept it to get push alerts on every account event.

> Forgot the password? Contact the branch manager to reset it. There is no self-serve reset.

### The four tabs
| Tab | What you see |
|-----|---------------|
| **Home** | Your balances per currency, with "You owe" / "We owe" badges. |
| **Transactions** | Every movement (deposit, withdrawal, commission, transfer) with currency and type filters. |
| **Shipments** | All your shipments — air or sea — with badges (received / in transit / payment due). |
| **Notifications** | System alerts (deposit registered, shipment received, etc.). You can mark all as read. |

### Tracking a shipment
1. From **Shipments**, tap the shipment.
2. You'll see details: count, weight, CBM, category, origin, date, and current status.
3. Tap **Tracking codes (n)** to open the codes for each individual piece.
4. Tap **Copy** next to any code to copy it.
5. The timeline below shows the shipment's history: received at hub / in transit / received at branch / ready for pickup / delivered.

### Web client portal (for clients who use a computer)
- The same login works on the website: open the system URL and sign in.
- You can print a **statement** from **Transactions → Print reports**.
- You can also see details of any sea container or air trip your shipment is linked to.

### Notification settings
- From the app: you can disable specific notification types (e.g. mute commission alerts while keeping shipment alerts).
- If notifications never arrive: make sure your phone OS lets the app send notifications.

---

## FAQ

**Q: I forgot my password.**  
A: Contact your branch manager or the system administrator. There is no self-serve reset yet.

**Q: The app says "Your account isn't linked to any branch."**  
A: The system admin must assign you to a branch from **Users / Branch Staff** before you can scan.

**Q: I scanned a sticker and saw "Sticker revoked".**  
A: Do not use that sticker. Discard it. Register the goods with a new sticker.

**Q: My scans aren't being uploaded.**  
A: Check your internet connection. Tap **Sync now** manually. If it persists, contact the ops engineer.

**Q: A client says their balance is wrong.**  
A: In the web admin, open the client's profile → transactions tab. Compare against the most recent receipt. If there's a real error, **reverse** the bad entry (do not delete it) and post a corrected one.

**Q: The Trial Balance is out of balance.**  
A: Open **Drift Detector** immediately. The cause is almost always a one-sided entry (DR without CR) or two legs of an entry posted at different FX rates.

**Q: How do I change the language?**  
A: On the web: language toggle at the top-right. On the employee and client apps: change the phone's system language — both apps follow the device locale.

---

## Contact

For anything this guide doesn't solve, escalate to:
- **Branch manager** for day-to-day operational questions.
- **System administrator** for permissions and password issues.
- **Ops engineer** for app, server, or network problems.
