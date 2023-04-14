<?php

use Illuminate\Support\Facades\Route;
use resuelveFormulas\Http\Controllers\ResuelveFormulasController;

Route::group(['middleware'=>['web','auth']],function(){

  // Route::get('resuelve-formulas-get-funciones', [ResuelveFormulasController::class, 'getFunciones']);
  // Route::post('resuelve-formulas-get-resultado', [ResuelveFormulasController::class, 'getResultado']);

});
