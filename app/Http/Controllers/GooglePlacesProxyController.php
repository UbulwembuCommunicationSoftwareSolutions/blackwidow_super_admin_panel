<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GooglePlacesProxyController extends Controller
{
    public function proxy(Request $request)
    {
        $endpoint = $request->input('endpoint', 'place/autocomplete/json');
        $params = $request->except('endpoint');
        $params['key'] = config('services.google.places_api_key');

        $url = 'https://maps.googleapis.com/maps/api/' . $endpoint;

        $response = Http::get($url, $params);

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type'));
    }
}
