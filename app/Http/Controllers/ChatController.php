<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function getMessages(Request $request, $ride) {}
    public function sendMessage(Request $request, $ride) {}
    public function initiateCall(Request $request, $ride) {}
    public function triggerEmergency(Request $request, $ride) {}
}
