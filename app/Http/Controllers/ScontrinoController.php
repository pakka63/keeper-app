<?php

namespace App\Http\Controllers;

use App\Scontrino;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScontrinoController extends Controller
{

    /**
     * Visualizza la lista degli scontrini da emettere
     *
      * @param Request $request
      * @return Illuminate\Http\Response
     */

    private $fields = ['id', 'id_documento', 'testo', 'prezzo', 'created_at', 'errore'];
    
    public function getNuovi(Request $request)
    {
      $inProva = $request->test? 1 : 0;
      $query =  Scontrino::where('stato', 0)->where('in_prova', $inProva);
      try {
          $result = $query->get($this->fields);
      } catch (Throwable $e) {
        $message = explode("\n",$e->getMessage());
        return $this->sendErr($message[0]);
      }
      //anche questo funziona =
      //$result =  Scontrino::select($this->fields)->where('stato', 0)->get();
      return $result->toJson();
    }
    
    public function destroyNuovi(Request $request)
    {
      $inProva = $request->test? 1 : 0;
      if(empty($request->id)) {
        return $this->sendErr("Parameter ID missing");
      }
      try {
        $scontrino = Scontrino::find($request->id);
        if($scontrino->stato == 0 && $scontrino->in_prova == $inProva) {
          $scontrino->delete();
        }
      } catch (Throwable $e) {
        $message = explode("\n",$e->getMessage());
        return $this->sendErr($message[0]);
      }
      return response()->noContent();
    }

    public function getDaInviare(Request $request)
    {
      $inProva = $request->test? 1 : 0;
      $fld = array_merge($this->fields, ['updated_at']);
      $result =  Scontrino::where('stato', 1)->where('in_prova', $inProva)->get($fld);
      return $result->toJson();
    } 

    public function getInviati(Request $request)
    {
      $inProva = $request->test? 1 : 0;
      $fld = array_merge($this->fields, ['updated_at']);
      $result =  Scontrino::where('stato', 2)->where('in_prova', $inProva)->get($fld);
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
        $ticket->stato=1;
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
    $answersByUrl = array();
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
      //Raggruppo gli scontrini da inviare per url e printer id
      $printer = $ticket->id_printer;
      $url = $ticket->response_url;
      $obj = /*(object)*/ array('ID_Documento' => $ticket->id_documento, "ticketNumber" => $ticket->id_scontrino, "dateTime" => $ticket->dataOraStampa);
      if (!isset($answersByUrl[$url])) {
        $answersByUrl[$url] = array();
      }
      if (!isset($answersByUrl[$url][$printer])) {
        $answersByUrl[$url][$printer] = array();
      }
      $answersByUrl[$url][$printer][$ticket->id] = $obj;
    } // foreach

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
    foreach ($answersByUrl as $url => $answers) {

      foreach ($answers as $printer => $tickets) {
        if ($token) {
          //Ho un token d'accesso e quindi faccio la post
          $payload = array(
            "serialNumber" => substr($printer, 0, 10),
            "printerModel" => substr($printer, 10),
            "Answers"      => array_values($tickets)
          );
        
          // @todo l'endPoint dovrebbe essere preso dal record...
          // $response = Http::withToken($token)->post($sapWS['EndPoint'], $payload);
          $response = Http::withToken($token)->post($url, $payload);
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

      $tot = Scontrino::where('id_documento', trim($ticket['ID_Documento']))->count();
      $ticket = Scontrino::create([
        'id_documento' => trim($ticket['ID_Documento']),
        'prezzo' => +$ticket['price'],
        'testo' => $ticket['Text'],
        'in_prova' => $input['systemType'] == 'TEST',
        'response_url' => $input['responseUrl'],
        'errore' => ($tot > 0 ? 'Documento già ricevuto' : ''),
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