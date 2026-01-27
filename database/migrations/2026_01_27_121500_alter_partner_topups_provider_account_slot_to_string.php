// database/migrations/2026_01_27_121500_alter_partner_topups_provider_account_slot_to_string.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_topups', function (Blueprint $table) {
            // Si estaba INT, pásala a VARCHAR
            $table->string('provider_account_slot', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('partner_topups', function (Blueprint $table) {
            // Reversa (solo si realmente quieres volver a INT)
            $table->unsignedTinyInteger('provider_account_slot')->nullable()->change();
        });
    }
};
