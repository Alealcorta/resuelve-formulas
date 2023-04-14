## resuelve-formulas 

<p align="center">
  <img src="https://img.shields.io/static/v1?label=php&message=^8.1&color=greem">
  
  <img src="https://img.shields.io/static/v1?label=Laravel&message=9.x&color=greem">  

  <img src="https://img.shields.io/static/v1?label=Vue.js&message=3.x&color=greem">  

  <img src="https://img.shields.io/static/v1?label=JQuery&message=3.x&color=greem"> 
    
  <a href="#">
    <img src="https://img.shields.io/static/v1?label=Stable&message=v1.0.0&color=blue" alt="Latest Stable Version">
  </a>

  <a href="#">
    <img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License">
  </a>
</p>


<b>resuelve-formulas</b> es una librería que le permite obtener el resultado de una formula cuyas funciones pueden estar anidadas.  



### Requerimientos
- `PHP: ^8.0`
- `laravel: ^9`
- `composer`




### Instalación

```

- En su terminal ejecute el siguiente comando: 
  - `composer require alealcorta/resuelve-formulas`



### El helper Formulas
Este helper le ayudará a obtener:
- El listado de funciones disponibles.
- El resultado de una formula.


### Instruciones para usar el helper Formulas

Para usar el helper `src/Helpers/Formulas` puede importarlo en sus controladores o donde usted lo necesite.

```php
//Ejemplo

<?php

namespace App\Http\Controller;

use resuelveFormulas\Helpers\Formulas;

class ExampleController
{
  return Formulas::getFunciones();
}
```

### DOCUMENTACIÓN de los métodos del helper Formulas

```php

/**
 * Retorna resultado de una formula
 */
$formula = "SUMATORIA(2,MAXIMO(3,5,6),7)";
Formulas::getResultado($formula);

// Respuesta de ejemplo
15

/**
 * Retorna listado de funciones disponibles
 */
Formulas::getFunciones();

// Respuesta de ejemplo
[
  "ABSOLUTO" => "Devuelve el número absoluto. Ej: ABSOLUTO(-5), devuelve 5",
  "ALEATORIO" => "Devuelve un número de tipo entero aleatorio entre dos números. Ej: ALEATORIO(1,5)",

  ...
]



```


