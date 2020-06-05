<?php

namespace App\Http\Controllers;

use App\Scontrino;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        if(empty($input['id']) || empty($input['id_scontrino']) || empty($input['id_printer']) ) {
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
        $ticket->id_printer = $input['id_printer'];
        $ticket->errore = $input['errore'] ??'';
        $ticket->stato=1; // <<--in prova
        $ticket->save();
        return response()->noContent();
    }
    /**
     * // invia a SAP gli scontrini indicati
     */
    public function inviaASAP(Request $request)
    {
        $token = false;

        $input = $request->all();

        if(empty($input['tickets']) || !is_array($input['tickets'])) {
            return $this->sendErr("Parameter 'tickets' missing or invalid");
        }


    /*
        $client = new Client(); //GuzzleHttp\Client
        $response = $client->get($sap['TokenEndpoint'], ['auth' => [$sap['clientId'], $sap['clientPwd']]);

JSON di risposta:
"Answers": [{
    "ID_Documento": "FT0123",

      "ticketNumber": "AA8364771",
      "dateTime": "0203201540",
      },{
    "ID_Documento": "FT0124",

      "ticketNumber": "AA8364772",
      "dateTime": "0203201541",
      },{
    "ID_Documento": "FT0125",

      "ticketNumber": "AA8364773",
      "dateTime": "0203201542",
     }]

        */
    $answers = array();
    $interattivo = !($input['afterPrint'] ?? false);
    foreach ($input['tickets'] as $i => $ticket) {
      if (empty($ticket['id'])) {
        if ($interattivo) {
          return $this->sendErr('Ticket item #' . $i + 1 . ' is invalid');
        } else {
          continue;
        }
      }
      $ticket = Scontrino::find($ticket['id']);
      if (empty($ticket)) {
        if ($interattivo) {
          return $this->sendErr("Ticket (id " . $ticket['id'] . ") not found", ($request->wantsJson() || $request->isJson()), 404);
        } else {
          continue;
        }
      }
      if ($ticket->stato != 1) { // invio solo quelli stampati
        if ($interattivo) {
          return $this->sendErr("Invalid ticket (id " . $ticket['id'] . ")", ($request->wantsJson() || $request->isJson()), 404);
        } else {
          continue;
        }
      }
      //Raggrupppo gli scontrini da inviare per printer id
      $printer = $ticket->id_printer;
      $obj = /*(object)*/ array('ID_Documento' => $ticket->id_documento, "ticketNumber" => $ticket->id_scontrino, "dateTime" => $ticket->dataOraStampa);
      if (!isset($answers[$printer])) {
        $answers[$printer] = array();
      }
      $answers[$printer][$ticket->id] = $obj;
    }

    $sapWS = config('sapWS');
    $response = Http::withBasicAuth($sapWS['Oauth2']['clientId'], $sapWS['Oauth2']['clientPwd'])->post($sapWS['Oauth2']['tokenEndpoint']);
    if (!$response->successful()) {
      $errTxt = "Access to SAP WS not allowed\n" . $response->getStatusCode() . ' - ' . $response->getReasonPhrase();
      if ($interattivo) {
        return $this->sendErr($errTxt, ($request->wantsJson() || $request->isJson()), 404);
      }
    } else {
      // Memorizzo il token di ritorno... per usarlo con le post successive
      $result = json_decode($response->getBody());
      $token = $result->access_token;
      $errTxt = '';
    }

    foreach ($answers as $printer => $tickets) {
      if ($token) {
        //Ho un token d'accesso e quindi faccio la post
        $payload = array(
          "serialNumber" => substr($printer, 0, 10),
          "printerModel" => substr($printer, 10),
          "Answers"      => array_values($tickets)
        );

        // @todo l'endPoint dovrebbe essere preso dal record...
        $response = Http::withToken($token)->post($sapWS['EndPoint'], $payload);
        if (!$response->successful()) {
          $errTxt = "Post to SAP Ws with error " . $response->getStatusCode() . '\n' . ($response->getBody() ?: $response->getReasonPhrase());
        } else {
          // ad. es.: " {"multimap:Message1":{"message":["Invoice REIT-25-2020 updated successfully","Invoice REIT-26-2020 updated successfully"]}}"
          $result = $response->getBody();
          $errTxt = '';
        };
      }
      // Qui registro l'esito dell'invio sullo scontrino
      if ($errTxt > '') {
        // Scrivo l'errore
        $this->markErrors(array_keys($tickets), $errTxt);
      } else {
        // Aggiorno gli scontrini
        $this->updateStatus(array_keys($tickets), 2);
      }
    }
    if ($interattivo && $errTxt > '') {
      return $this->sendErr($errTxt, ($request->wantsJson() || $request->isJson()), 404);
    } else {
      return response()->noContent();
    }
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

    $bodyContent = $request->getContent();
    Log::debug($bodyContent);

    if (empty($input['systemType'])) {
      return $this->sendErr("Parameter 'systemType' missing");
    }
    if ($input['systemType'] != 'TEST' && $input['systemType'] != 'PROD') {
      return $this->sendErr("Parameter 'systemType' is invalid");
    }
    if (empty($input['responseUrl'])) {
      return $this->sendErr("Parameter 'responseUrl' missing");
    }
    if (empty($input['tickets']) || !is_array($input['tickets'])) {
      return $this->sendErr("Parameter 'tickets' missing or invalid");
    }
    foreach ($input['tickets'] as $i => $ticket) {
      if (empty($ticket['ID_Documento']) || empty($ticket['Text']) || empty($ticket['price']) || !is_numeric($ticket['price'])) {
        return $this->sendErr('Ticket item #' . $i + 1 . ' is invalid');
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
    return response()->json([], 204);
  }

  private function sendErr($message, $isJson = true, $status = 400)
  {
    if ($isJson) {
      return response()->json(array('error' => $message), $status);
    } else {
      return response($message, $status);
    }
  }

  private function markErrors($idList, $errorTxt)
  {
    $aggiornati = DB::table('scontrini')
    ->whereIn('id', $idList)
      ->update(['errore' => substr($errorTxt, 0, 190)]);
  }

  private function updateStatus($idList, $status)
  {
    $aggiornati = DB::table('scontrini')
      ->whereIn('id', $idList)
      ->update(['stato' => $status]);
  }
}