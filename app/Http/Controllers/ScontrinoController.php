<?php

namespace App\Http\Controllers;

use App\Scontrino;
use Illuminate\Http\Request;

class ScontrinoController extends Controller
{

    /**
     * Visualizza la lista degli scontrini da emettere
     *
      * @param Request $request
      * @return Illuminate\Http\Response
     */

    private $fields = ['id', 'id_documento', 'testo', 'prezzo', 'created_at', 'errore'];
    public function getNuovi()
    {
        $result =  Scontrino::where('stato', 0)->get($this->fields);
        //anche questo funziona =
        //$result =  Scontrino::select($this->fields)->where('stato', 0)->get();
        return $result->toJson();
    } 
    public function getDaInviare()
    {
        $fld = array_merge($this->fields, ['updated_at']);
        $result =  Scontrino::where('stato', 1)->get($fld);
        return $result->toJson();
    } 

    public function getInviati()
    {
        $fld = array_merge($this->fields, ['updated_at']);
        $result =  Scontrino::where('stato', 2)->get($fld);
        return $result->toJson();
    } 

    /**
     * Riceve in POST i nuovi importi docimenti di cui occorrerà stampare lo scontrino.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeData(Request $request)
    {
        $input = $request->all();
        if(empty($input['systemType'])) {
            return $this->sendErr("Parameter 'systemType' missing");
        }
        if($input['systemType'] != 'TEST' && $input['systemType'] != 'PROD') {
            return $this->sendErr("Parameter 'systemType' is invalid");
        }
        if(empty($input['responseUrl'])) {
            return $this->sendErr("Parameter 'responseUrl' missing");
        }
        if(empty($input['tickets']) || !is_array($input['tickets'])) {
            return $this->sendErr("Parameter 'tickets' missing or invalid");
        }
        foreach ($input['tickets'] as $i => $ticket) {
            if(empty($ticket['ID_Documento']) || empty($ticket['Text']) || empty($ticket['price']) || !is_numeric($ticket['price'])) {
                return $this->sendErr('Ticket item #' . $i+1 . ' is invalid');
            }
        }
        // Qui adesso archivio i record.
        foreach ($input['tickets'] as $i => $ticket) {
            $ticket = Scontrino::create([
                'id_documento' => $ticket['ID_Documento'],
                'prezzo' => +$ticket['price'],
                'testo' => $ticket['Text'],
                'in_prova' => $input['systemType'] == 'TEST',
                'response_url' => $input['responseUrl']
            ]);
        }

        //dd($input);
        return response()->json([],204);
    }

    private function sendErr($message)
    {
        return response()->json(array('error' => $message),400);
    }

}
