<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;

class CheckPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-pages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check seeded pages in database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pages = Page::orderBy('id')->get(['id', 'name', 'is_active']);

        $this->info('Total pages: '.$pages->count());
        $this->line('');

        $this->table(['ID', 'Name', 'Active'], $pages->map(function ($page) {
            return [
                $page->id,
                $page->name,
                $page->is_active ? 'Yes' : 'No',
            ];
        })->toArray());

        return 0;
    }
}
