<?php

namespace Database\Seeders;

use App\Models\BranchDirectory;
use Illuminate\Database\Seeder;

class BranchDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/branches.json');

        if (! file_exists($path)) {
            $this->command->error("File not found: $path");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        $rows = collect($rows)->map(fn ($r) => [
            'code'        => $r['code'],
            'name'        => $r['name'],
            'branch_type' => $this->typeFor($r['code']),
            'created_at'  => now(),
            'updated_at'  => now(),
        ])->all();

        foreach (array_chunk($rows, 100) as $chunk) {
            BranchDirectory::upsert($chunk, ['code'], ['name', 'branch_type', 'updated_at']);
        }

        $this->command->info(count($rows) . ' branches seeded.');
    }

private function typeFor(string $code): string
{
    $n = (int) $code;

    return match (true) {
        $n === 999 || $n === 998 => 'Head office',
        $n >= 985 && $n <= 996   => 'District',
        default                  => 'Branch',   // includes 997 Issue Center
    };
}
}