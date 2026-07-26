<?php

use App\Http\Controllers\CmsPublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public CMS & Legal Routes
|--------------------------------------------------------------------------
*/

Route::get('/cancellation-reasons', [CmsPublicController::class, 'cancellationReasons']);
Route::get('/faq-categories', [CmsPublicController::class, 'faqCategories']);
Route::get('/faqs', [CmsPublicController::class, 'faqs']);
Route::post('/contact-us', [CmsPublicController::class, 'submitContact']);
Route::get('/privacy-policy', [CmsPublicController::class, 'privacyPolicy']);
Route::get('/terms-and-conditions', [CmsPublicController::class, 'termsAndConditions']);
Route::get('/legal-pages/{slug}', [CmsPublicController::class, 'legalPage']);
