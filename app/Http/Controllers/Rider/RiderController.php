<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    public function dashboard(Request $request) {}
    public function vehicleTypes(Request $request) {}
    public function fareEstimate(Request $request) {}
    public function requestRide(Request $request) {}
    public function scheduleRide(Request $request) {}
    public function currentRide(Request $request) {}
    public function cancelRide(Request $request, $ride) {}
    public function driverDetails(Request $request, $ride) {}
    public function rideHistory(Request $request) {}
    public function rideReceipt(Request $request, $ride) {}
    public function reviewDriver(Request $request, $ride) {}
    public function getWallet(Request $request) {}
    public function walletTopup(Request $request) {}
    public function paymentMethods(Request $request) {}
    public function addPaymentMethod(Request $request) {}
}
