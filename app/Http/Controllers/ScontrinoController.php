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
     * Riceve in POST id, id_scontrino ed eventuale errore, degli scontrini stampati
     */
    public function setStampati(Request $request)
    {
        $input = $request->all();

        if(empty($input['id']) || empty($input['id_scontrino'])) {
            return $this->sendErr("Parameters missing");
        }
        $ticket = Scontrino::find($input['id']);
        if(empty($ticket)) {
            return $this->sendErr("Ticket not found", ($request->wantsJson() || $request->isJson()), 404);
        }
        if($ticket->stato!=0) {
            return $this->sendErr("Ticket status not compatible", ($request->wantsJson() || $request->isJson()));
        }
        $ticket->id_scontrino = $input['id_scontrino'];
        $ticket->errore = $input['errore'] ??'';
        $ticket->stato=1;
        $ticket->save();
        return response()->noContent();
    }
    /**
     * Riceve in POST id degli scontrini inviati
     */
    public function setInviati(Request $request)
    {
        $input = $request->all();

        if(empty($input['id'])) {
            return $this->sendErr("Parameters missing");
        }
        $ticket = Scontrino::find($input['id']);
        if(empty($ticket)) {
            return $this->sendErr("Ticket not found", ($request->wantsJson() || $request->isJson()), 404);
        }
        if($ticket->stato!=1) {
            return $this->sendErr("Ticket status not compatible", ($request->wantsJson() || $request->isJson()));
        }
        $ticket->errore = $input['errore'] ??'';
        $ticket->stato=2;
        $ticket->save();
        return response()->noContent();
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

    private function sendErr($message, $isJson=true, $status=400)
    {
        if($isJson) {
            return response()->json(array('error' => $message),$status);
        } else {
            return response($message ,$status);
        }
    }

}
