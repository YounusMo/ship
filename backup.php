<?php
ini_set('memory_limit', '5G'); // Increase the memory limit to 2GB

$directory = __DIR__.'/backups_sub';
$files = array_diff(scandir($directory), array('..', '.'));
$fileCount = count($files);
$files = array_values(array_diff(scandir($directory), array('..', '.')));

if($fileCount > 14){
    unlink($directory.'/'.$files[0]);
}

$mysqlUserName      = 'qubtangroup_user';
$mysqlPassword      = 'Dx1nMcIu(rP)Q?hC';
$mysqlHostName      = 'localhost';
$DbName             = 'qubtangroup_sub';
$tables             = ['branches','branches_transactions','clients','clients_transactions','containers_sea','containers_sea_fees','containers_sky','containers_sky_fees','customs_brokers','customs_brokers_transactions','	store_out_sea','store_out_sky','store_sea','store_sky','suppliers','suppliers_transactions','treasury_transactions','users']; // Add specific tables in this array if needed
$tiemzone  = "Europe/Istanbul";
date_default_timezone_set($tiemzone);
Export_Database($mysqlHostName, $mysqlUserName, $mysqlPassword, $DbName, $tables);

function Export_Database($host, $user, $pass, $name, $tables = false, $backup_name = false)
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $mysqli = new mysqli($host, $user, $pass, $name);
        $mysqli->set_charset('utf8mb4'); // Ensure UTF-8 support

        $queryTables = $mysqli->query('SHOW TABLES');
        $target_tables = [];
        while ($row = $queryTables->fetch_row()) {
            $target_tables[] = $row[0];
        }

        if ($tables !== false) {
            $target_tables = array_intersect($target_tables, $tables);
        }

        $sql_data = "-- Database: `$name`\n\n";
        
        foreach ($target_tables as $table) {
            $result = $mysqli->query("SELECT * FROM `$table`");

            // Create table structure
            $createTableQuery = $mysqli->query("SHOW CREATE TABLE `$table`")->fetch_row();
            $sql_data .= "\n\n" . $createTableQuery[1] . ";\n\n";

            // Insert table data
            while ($row = $result->fetch_assoc()) {
                $sql_data .= "INSERT INTO `$table` VALUES (";
                $first = true;
                foreach ($row as $value) {
                    if (!$first) $sql_data .= ', ';
                    // Handle NULL values properly
                    if (is_null($value)) {
                        $sql_data .= 'NULL';
                    } else {
                        $sql_data .= '"' . $mysqli->real_escape_string($value) . '"';
                    }
                    $first = false;
                }
                $sql_data .= ");\n";
            }
        }

        $backup_name = $backup_name ? $backup_name : $name . ".sql";
        $file_name = 'backups/' . date('Y-m-d') . ' ' . date('H:i') . '.sql';

        if (!file_exists('backups')) {
            mkdir('backups', 0777, true);
        }

        if (!file_exists($file_name)) {
            if (file_put_contents($file_name, $sql_data) === false) {
                throw new Exception("Failed to write to file $file_name");
            }
        }

        // echo "Database successfully exported to $file_name.";

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
