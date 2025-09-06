<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Client;

echo "=== Testing Contact Number Separation ===\n\n";

// Find a client with projects
$client = Client::with(['projects', 'contactNumbers'])->first();

if (! $client) {
    echo "No clients found. Please create a client first.\n";
    exit;
}

echo "Client: {$client->name} (ID: {$client->id})\n";
echo 'Client Contact Numbers: '.$client->contactNumbers->count()."\n";

// Display client contact numbers
foreach ($client->contactNumbers as $contactNumber) {
    echo "  - {$contactNumber->name}: {$contactNumber->number} (Type: {$contactNumber->type}, Project ID: ".($contactNumber->project_id ?? 'NULL').")\n";
}

// Find projects for this client
$projects = $client->projects()->with('contactNumbers')->get();

echo "\nProjects for this client:\n";
foreach ($projects as $project) {
    echo "  Project: {$project->project_name} (ID: {$project->id})\n";
    echo '  Project Contact Numbers: '.$project->contactNumbers->count()."\n";

    foreach ($project->contactNumbers as $contactNumber) {
        echo "    - {$contactNumber->name}: {$contactNumber->number} (Type: {$contactNumber->type}, Project ID: {$contactNumber->project_id})\n";
    }
}

echo "\n=== Verification ===\n";
echo "✓ Client contact numbers should have project_id = NULL\n";
echo "✓ Project contact numbers should have project_id = [project_id]\n";
echo "✓ Both should have the same client_id but serve different purposes\n";
