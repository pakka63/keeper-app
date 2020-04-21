<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScontriniTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('scontrini', function (Blueprint $table) {
            $table->id();
            $table->string('id_documento'); // Nr. fattura
            $table->string('testo'); // Testo scontrino
            $table->decimal('prezzo');
            $table->smallInteger('stato')->default(0); // 0=nuovo, 1=stampato/emesso, 2=esito inviato al SAP
            $table->string('id_scontrino')->nullable();
            $table->boolean('in_prova')->default(false);
            $table->string('errore')->nullable(); // eventuale messaggio di errore, se stato = 0 => errore instampa, se stato = 1 => errore invio SAP
            $table->string('response_url'); // URL Server a cui inviare la risposta
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('scontrini');
    }
}
