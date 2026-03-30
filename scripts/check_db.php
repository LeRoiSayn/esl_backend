<?php
// One-off DB check script. Run from project backend directory: php scripts/check_db.php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
    $connection = DB::connection();
    $databaseName = $connection->getDatabaseName();
    echo "Connected to database: {$databaseName}\n";

    // Count users
    $count = DB::table('users')->count();
    echo "Users count: {$count}\n";

    // Show up to 10 admin users
    $admins = DB::table('users')->where('role', 'admin')->limit(10)->get();
    echo "Admin users (up to 10):\n";
    foreach ($admins as $a) {
        echo "- id={$a->id} username={$a->username} email={$a->email} is_active={$a->is_active}\n";
    }

    // If no admin found, show first 5 users
    if (count($admins) === 0) {
        echo "No admin users found. Showing first 5 users:\n";
        $users = DB::table('users')->limit(5)->get();
        foreach ($users as $u) {
            echo "- id={$u->id} username={$u->username} email={$u->email} role={$u->role} is_active={$u->is_active}\n";
        }
    }

} catch (Exception $e) {
    echo "DB connection or query failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
