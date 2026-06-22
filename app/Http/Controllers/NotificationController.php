<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request) {}
    public function markAsRead(Request $request, $notification) {}
    public function markAllAsRead(Request $request) {}
}
