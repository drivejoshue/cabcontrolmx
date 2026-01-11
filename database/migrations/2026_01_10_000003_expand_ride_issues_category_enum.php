<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("
            ALTER TABLE ride_issues
            MODIFY category ENUM(
              'safety','overcharge','route','driver_behavior','passenger_behavior','vehicle',
              'lost_item','payment','app_problem','other'
            ) NOT NULL DEFAULT 'other'
        ");
    }

    public function down(): void {
        DB::statement("
            ALTER TABLE ride_issues
            MODIFY category ENUM(
              'safety','overcharge','route','driver_behavior','vehicle',
              'lost_item','payment','app_problem','other'
            ) NOT NULL DEFAULT 'other'
        ");
    }
};