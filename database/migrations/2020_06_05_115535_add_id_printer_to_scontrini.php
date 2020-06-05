<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdPrinterToScontrini extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('scontrini', function (Blueprint $table) {
            $table->string('id_printer')->nullable(); // Nr. seriale stampante fiscale
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('scontrini', function (Blueprint $table) {
            $table->dropColumn('id_printer');
        });
    }
}
