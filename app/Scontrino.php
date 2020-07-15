<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Scontrino extends Model
{
    const TABLE = 'scontrini';
    protected $table = self::TABLE;
    protected $dateTimeFormat = 'Y/m/d H:i'; // <-- lo uso per i getters delle date. Nb.: data rovescia per preservare il sort. verrà formattata da Vue

    protected $fillable = [
        'id_documento', 'prezzo', 'testo', 'in_prova', 'response_url', 'errore'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->tz = config('app.timezone');
    } 

    //Per formattare i risultati, uso i Mutators (https://laravel.com/docs/7.x/eloquent-mutators)
    public function getCreatedAtAttribute($value) {
        return Carbon::parse($value)->timezone($this->tz)->format($this->dateTimeFormat);
    }

    public function getUpdatedAtAttribute($value) {
        return Carbon::parse($value)->timezone($this->tz)->format($this->dateTimeFormat);
    }

    public function getDataOraStampaAttribute() {
        return Carbon::parse($this->attributes['updated_at'])->timezone($this->tz)->format('ymdHi');
    }
    public function getPrezzoAttribute($value) {
        return +$value; // Passo il dato come numerico in modo da conserire il sort corretto da VUE
//        return $value.'€';
    }

}
