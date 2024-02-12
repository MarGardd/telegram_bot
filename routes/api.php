<?php

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('questions_list', function(){ return Question::all(['id', 'question_text']); });

Route::get('paychecks', [Controller::class, 'index']);
Route::post('paychecks', [Controller::class, 'store']);
Route::post('paychecks/{order_id}', [Controller::class, 'update']);
Route::post('paychecks/{order_id}/add-photo', [Controller::class, 'addPhoto']);
Route::get('paychecks/photo/{file_id}', [Controller::class, 'showPhoto'])->name('show-photo');
Route::delete('paychecks/photo/{file_id}', [Controller::class, 'deletePhoto']);
Route::post('paychecks/{order_id}/check', [Controller::class, 'checked']);
Route::post('paychecks/{order_id}/archive', [Controller::class, 'archive']);
Route::post('paychecks/{order_id}/restore', [Controller::class, 'restore']);
Route::delete('paychecks/{order_id}', [Controller::class, 'delete']);

Route::get('companies', [Controller::class, 'getCompanies']);
Route::post('companies', [Controller::class, 'addCompany']);
Route::post('companies/{company_id}', [Controller::class, 'updateCompany']);
Route::delete('companies/{company_id}', [Controller::class, 'deleteCompany']);

Route::get('companies/{company_id}/projects', [Controller::class, 'getProjects']);
Route::post('companies/{company_id}/projects', [Controller::class, 'addProject']);
Route::post('companies/{company_id}/projects/{project_id}', [Controller::class, 'updateProject']);
Route::delete('companies/{company_id}/projects/{project_id}', [Controller::class, 'deleteProject']);

Route::get('payment_methods', [Controller::class, 'getPaymentMethods']);
Route::post('payment_methods', [Controller::class, 'addPaymentMethod']);
Route::post('payment_methods/{payment_method_id}', [Controller::class, 'updatePaymentMethod']);
Route::delete('payment_methods/{payment_method_id}', [Controller::class, 'deletePaymentMethod']);

Route::get('sheet', [Controller::class, 'sheet']);

Route::get('users', [Controller::class, 'getUsers']);
Route::post('users', [Controller::class, 'createUser']);
Route::post('users/{user_id}', [Controller::class, 'recoverUser']);
Route::delete('users/{user_id}', [Controller::class, 'deleteUser']);
Route::post('admin/{user_id}', [Controller::class, 'setAdmin']);
Route::delete('admin/{user_id}', [Controller::class, 'deleteAdmin']);