<?php

namespace resuelveFormulas\Helpers;

class Util {
    public static function av($arr, $key, $default=null) {
        return (isset($arr[$key]))?$arr[$key]:$default;
    }

}; 

class Scope {
    protected $_builder = null;
    protected $_children_contexts = array();
    protected $_raw_content = array();
    protected $_operations = array();
    protected $options = array();
    protected $depth = 0;
    protected $_mifuncion;

    const T_NUMBER = 1;
    const T_OPERATOR = 2;
    const T_SCOPE_OPEN = 3;
    const T_SCOPE_CLOSE = 4;
    const T_FUNC_SCOPE_OPEN = 5;
    const T_SEPARATOR = 6;
    const T_VARIABLE = 7; 
    const T_STR = 8;

    public function __construct(&$options, $depth=0) {
        $this->options = &$options;
        if (!isset($this->options['variables']))
            $this->options['variables'] = array();

        $this->depth = $depth;
        $maxdepth = Util::av($options, 'maxdepth',0);
        if ( ($maxdepth) && ($this->depth > $maxdepth) )
            //throw new \spex\exceptions\MaxDepthException($maxdepth);
            die("error");
    }

    public function setBuilder(Parser $builder ) {
        $this->_builder = $builder;
    }

    public function __toString() {
        return implode('', $this->_raw_content);
    }

    protected function addOperation( $operation ) {
        $this->_operations[] = $operation;
    }

    protected function searchFunction ($functionName) {

        $functions = Util::av($this->options, 'functions', array());

        $func = Util::av($functions, $functionName);

        if (!$func)
          die("error: ".$functionName);

        return $func;
    }

    /**
     * handle the next token from the tokenized list. example actions
     * on a token would be to add it to the current context expression list,
     * to push a new context on the the context stack, or pop a context off the
     * stack.
     */
    public function handleToken( $token ) {
        $type = null;
        $data = array();

        if ( in_array( $token, array('*','/','+','-','^','=') ) )
            $type = self::T_OPERATOR;
        if ( $token == ',' )
            $type = self::T_SEPARATOR;
        if ( $token === ')' )
            $type = self::T_SCOPE_CLOSE;
        if ( $token === '(' )
            $type = self::T_SCOPE_OPEN;
        if ( preg_match('/^([a-zA-Z_]+)\($/', $token, $matches) ) {
            $data['function'] = $matches[1];
            $type = self::T_FUNC_SCOPE_OPEN;
        }

        if ( is_null( $type ) ) {
            if ( is_numeric( $token ) ) {
                $type = self::T_NUMBER;
                $token = (float)$token;
            } elseif (preg_match('/^".*"$|^\'.*\'$/', $token)) {
                $type = self::T_STR;
            } elseif (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token)) {
                $type = self::T_VARIABLE;
            } else
                  echo "**".$token."**";
        }

        switch ( $type ) {
            case self::T_NUMBER:
            case self::T_OPERATOR:
                $this->_operations[] = $token;
                break;
            case self::T_STR:
                $delim = $token[0];
                $this->_operations[] = str_replace('\\'.$delim, $delim, substr($token, 1, -1)) ;
                break;
            case self::T_VARIABLE:
                $this->_operations[] = array('v', $token);
                break;
            case self::T_SEPARATOR:
                break;
            case self::T_SCOPE_OPEN:
                $this->_builder->pushContext( new Scope($this->options, $this->depth+1) );
            break;
            case self::T_FUNC_SCOPE_OPEN:
                $this->_builder->pushContext( new FunScope($this->options, $this->depth+1, $this->searchFunction($data['function'])) );
            break;
            case self::T_SCOPE_CLOSE:
                $scope_operation = $this->_builder->popContext();
                $new_context = $this->_builder->getContext();
                if ( is_null( $scope_operation ) || ( ! $new_context ) ) {
                    # this means there are more closing parentheses than openning
                    throw new \spex\exceptions\OutOfScopeException();
                }
                $new_context->addOperation( $scope_operation );
            break;
            default:
                die($token);
            break;
        }
    }

    private function isOperation($operation) {
        return ( in_array( $operation, array('^','*','/','+','-','='), true ) );
    }

    protected function setVar($var, $value) {
        $this->options['variables'][$var] = $value;
    }

    protected function getVar($var) {
        return \spex\Util::av($this->options['variables'], $var, 0);
    }

    protected function getValue($val) {
        if (is_array($val)) {
            switch (\spex\Util::av($val, 0)) {
                case 'v': return $this->getVar(\spex\Util::av($val, 1));
                default:
                    throw new \spex\exceptions\UnknownValueException();
            }
        }
        return $val;
    }
    /**
     * order of operations:
     * - parentheses, these should all ready be executed before this method is called
     * - exponents, first order
     * - mult/divi, second order
     * - addi/subt, third order
     */
    protected function expressionLoop( & $operation_list ) {
        //while ( list( $i, $operation ) = each ( $operation_list ) ) 

        //while (list($key, $callback) = each($callbacks)) {

        foreach ($operation_list as $i => $operation) 
        {
            if ( ! $this->isOperation($operation) )
                continue;

            $left =  isset( $operation_list[ $i - 1 ] ) ? $operation_list[ $i - 1 ] : null;
            $right = isset( $operation_list[ $i + 1 ] ) ? $operation_list[ $i + 1 ] : null;

            if ( (is_array($right)) && ($right[0]=='v') )
                $right = $this->getVar($right[1]);
            if ( ($operation!='=') && ( (is_array($left)) && ($left[0]=='v') ) )
                $left = $this->getVar($left[1]);

            if ( is_null( $right ) ) throw new \Exception('syntax error');

            $first_order = ( in_array('^', $operation_list, true) );
            $second_order = ( in_array('*', $operation_list, true ) || in_array('/', $operation_list, true ) );
            $third_order = ( in_array('-', $operation_list, true ) || in_array('+', $operation_list, true )|| in_array('=', $operation_list, true ) );
            $remove_sides = true;
            if ( $first_order ) {
                switch( $operation ) {
                    case '^': $operation_list[ $i ] = pow( (float)$left, (float)$right ); break;
                    default: $remove_sides = false; break;
                }
            } elseif ( $second_order ) {
                switch ( $operation ) {
                    case '*': $operation_list[ $i ] = (float)($left * $right); break;
                    case '/':
                        if ($right==0)
                            throw new \spex\exceptions\DivisionByZeroException();
                        $operation_list[ $i ] = (float)($left / $right); break;
                    default: $remove_sides = false; break;
                }
            } elseif ( $third_order ) {
                switch ( $operation ) {
                    case '+': $operation_list[ $i ] = (float)($left + $right);  break;
                    case '-': $operation_list[ $i ] = (float)($left - $right);  break;
                    case '=': $this->setVar($left[1], $right); $operation_list[$i]=$right; break;
                    default: $remove_sides = false; break;
                }
            }

            if ( $remove_sides ) {
                if (!$this->isOperation($operation_list[ $i + 1 ]))
                    unset($operation_list[ $i + 1 ]);
                unset ($operation_list[ $i - 1 ] );
                $operation_list = array_values( $operation_list );
                reset( $operation_list );
            }
        }
        if ( count( $operation_list ) === 1 ) {
            $val = end($operation_list );
            return $this->getValue($val);
        }
        return $operation_list;
    }


    # order of operations:
    # - sub scopes first
    # - multiplication, division
    # - addition, subtraction
    # evaluating all the sub scopes (recursivly):
    public function evaluate() {
        foreach ( $this->_operations as $i => $operation ) {
            if ( is_object( $operation ) ) {
                $this->_operations[ $i ] = $operation->evaluate();
            }
        }

        $operation_list = $this->_operations;

        while ( true ) {
            $operation_check = $operation_list;
            $result = $this->expressionLoop( $operation_list );

            if ( $result !== false ) return $result;

            if ( $operation_check === $operation_list ) {
                break;
            } else {
                $operation_list = array_values( $operation_list );
                reset( $operation_list );
            }
        }
        throw new \Exception('failed... here');
    }
}

class FunScope extends Scope {
    private $fun = null;

    public function __construct(&$options, $depth, $callable) {
        parent::__construct($options, $depth);
        $this->fun = $callable;
    }

    public function evaluate() {
        $arguments = parent::evaluate();
        return call_user_func_array($this->fun, (is_array($arguments))?$arguments:array( $arguments ) );
    }
}


/**
 * this model handles the tokenizing, the context stack functions, and
 * the parsing (token list to tree trans).
 * as well as an evaluate method which delegates to the global scopes evaluate.
 */

class Parser {
    protected $_content = null;
    protected $_context_stack = array();
    protected $_tree = null;
    protected $_tokens = array();
    protected $_options;

    public function __construct($options = array(), $content = null) {
        $this->_options = $options;

        if ( $content ) {
            $this->set_content( $content );
        }
    }

    /**
     * this function does some simple syntax cleaning:
     * - removes all spaces
     * - replaces '**' by '^'
     * then it runs a regex to split the contents into tokens. the set
     * of possible tokens in this case is predefined to numbers (ints of floats)
     * math operators (*, -, +, /, **, ^) and parentheses.
     */
    public function tokenize() {
        $this->_content = str_replace(array("\n","\r","\t"), '', $this->_content);
        $this->_content = preg_replace('~"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"(*SKIP)(*F)|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'(*SKIP)(*F)|\s+~', '', $this->_content);
        $this->_content = str_replace('**', '^', $this->_content);
        $this->_content = str_replace('PI', (string)PI(), $this->_content);
        $this->_tokens = preg_split(
            '@([\d\.]+)|([a-zA-Z_]+\(|,|=|\+|\-|\*|/|\^|\(|\))@',
            $this->_content,
            null,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        return $this;
    }

    /**
     * this is the the loop that transforms the tokens array into
     * a tree structure.
     */
    public function parse() {
        # this is the global scope which will contain the entire tree
       $this->pushContext( new Scope($this->_options) );
        foreach ( $this->_tokens as $token ) {
            # get the last context model from the context stack,
            # and have it handle the next token
            $this->getContext()->handleToken( $token );
        }
        $this->_tree = $this->popContext();

        return $this;
    }

    public function evaluate() {
        if ( ! $this->_tree ) {
            throw new \spex\exceptions\ParseTreeNotFoundException();
        }
        return $this->_tree->evaluate();
    }

    /*** accessors and mutators ***/

    public function getTree() {
        return $this->_tree;
    }

    public function setContent($content = null) {
        $this->_content = $content;
        return $this;
    }

    public function getTokens() {
        return $this->_tokens;
    }


    /*******************************************************
     * the context stack functions. for the stack im using
     * an array with the functions array_push, array_pop,
     * and end to push, pop, and get the current element
     * from the stack.
     *******************************************************/

    public function pushContext(  $context ) {
        array_push( $this->_context_stack, $context );
        $this->getContext()->setBuilder( $this );
    }

    public function popContext() {
        return array_pop( $this->_context_stack );
    }

    public function getContext() {
        return end( $this->_context_stack );
    }
}

class Formulas
{
    public static function getResultado($formula) {

        $config = array(
            'maxdepth' => 9999999999,
            'functions' => array(
                // 'sin' => function ($rads) { return sin(deg2rad($rads)); },
                // 'upper' => function ($str) { return strtoupper($str); },
                // 'fact' => function ($n) { $r=1; for ($i=2; $i<=$n; ++$i) $r*=$i; return $r; },
                // 'word' => function ($text, $nword) { $words=explode(' ', $text); return (isset($words[$nword]))?$words[$nword]:''; },
                
                'PROMEDIO' => function () 
                { 
                    $arr = func_get_args();
                    $res_sum = 0;
                    foreach ($arr as $value) 
                    {
                        $res_sum = $res_sum + $value;
                    }

                    return $res_sum/count($arr);
                }, 
                'PORCENTAJE' => function ($num,$porc) 
                { 
                    return ($porc*$num)/100;
                },         
                'REGLA_TS' => function ($a,$b,$c) 
                { 
                    return ($c*$b)/$a;
                },  
                'REDONDEO_DECIMALES' => function ($a,$b) 
                { 
                    $cant_dec = pow(10,$b);
                    $salida = $a * $cant_dec;
                    $salida = (int) $salida;
                    $salida = $salida / $cant_dec;

                    return $salida;
                },         
                'CONDICIONAL' => function ($a,$b,$c) 
                { 
                    if($a)
                        return $b;
                    else
                        return $c;
                }, 
                'ES_MAYOR' => function ($a,$b) 
                { 
                    if($a > $b)
                        return 1;
                    else
                        return 0;
                },  
                'ES_MENOR' => function ($a,$b) 
                { 
                    if($a < $b)
                        return 1;
                    else
                        return 0;
                },      
                'ES_IGUAL' => function ($a,$b) 
                { 
                    if($a == $b)
                        return 1;
                    else
                        return 0;
                }, 
                'ES_DISTINTO' => function ($a,$b) 
                { 
                    if($a != $b)
                        return 1;
                    else
                        return 0;
                },          
                'ES_MAYOR_IGUAL' => function ($a,$b) 
                { 
                    if($a >= $b)
                        return 1;
                    else
                        return 0;
                },  
                'ES_MENOR_IGUAL' => function ($a,$b) 
                {
                    if($a <= $b)
                        return 1;
                    else
                        return 0;
                },                             
                'COUNT' => function () 
                { 
                    $arr = func_get_args();
                    return count($arr);
                },       
                'MAXIMO' => function () 
                { 
                    $arr = func_get_args();

                    $max = -99999999999999999;
                    foreach ($arr as $value) {
                        if($value > $max)
                            $max = $value;
                    }
                    return $max;
                },   
                'MINIMO' => function () 
                { 
                    $arr = func_get_args();

                    $min = 99999999999999999;
                    foreach ($arr as $value) {
                        if($value < $min)
                            $min = $value;
                    }
                    return $min;
                },
                'SUMATORIA' => function () 
                { 
                    $arr = func_get_args();
                    $res_sum = 0;
                    foreach ($arr as $value) 
                    {
                        $res_sum = $res_sum + $value;
                    }

                    return $res_sum;
                },   
                'PERSONALIZADO' => function($a)
                {
                    return $a;       
                },   
                'EXPONENCIAL' => function($a, $b)
                {
                    return pow($a, $b);       
                },   
                'RAIZ' => function($a, $b)
                {
                    return pow($a, 1/$b);       
                },
                'ABSOLUTO' => function($a)
                {
                    return abs($a);
                },
                'AND' => function()
                {
                    $arr = func_get_args();
                    foreach ($arr as $value)
                    {
                        if($value == 0)
                            return 0;
                    }
                    return 1;
                },
                'OR' => function()
                {
                    $arr = func_get_args();
                    foreach ($arr as $value)
                    {
                        if($value == 1)
                            return 1;
                    }
                    return 0;
                },
                'NOT' => function($a)
                {
                    if($a)
                        return 0;
                    else
                        return 1;
                },
                'REDONDEO' => function($num,$cant_decimales=0)
                {
                    return round($num,$cant_decimales);
                },
                'REDONDEO_ARRIBA' => function($num)
                {
                    return ceil($num);
                },
                'REDONDEO_ABAJO' => function($num)
                {
                    return floor($num);
                },
                'ALEATORIO' => function($num1,$num2)
                {
                    return rand($num1,$num2);
                }            
            )
        );

        $builder = new Parser($config);

        return $result = $builder->setContent($formula)->tokenize()->parse()->evaluate();
    }


    public static function getFunciones() {

        $functions = array(        
            'PROMEDIO' => "Busca el promedio entre 2 o mas valores. Ej: PROMEDIO(1,2,3), devuelve 2",
            'PORCENTAJE' => 'Calcula el procentaje de un número. Ej: PORCENTAJE(50,10), calcula el 10% de 50, devuelve 5',
            'REGLA_TS' => 'Realiza regla de tres simple. Ej: REGLA_TS(100,500,10) realiza 100 es a 500, como 10 es a X, devuelve 50',  
            'REDONDEO_DECIMALES' => 'Devuelve el número ingresado con la cantidad de decimales indicada. Ej: REDONDEO_DECIMALES(3.14, 1), devuelve 3.1',         
            'CONDICIONAL' => 'Evalua una condión ingresada como primer parámetro, si es verdadero devuelve el segundo parámetro , si es falso devuelve el tercer parámetro. Ej: CONDICIONAL(ES_MAYOR(5,2), 15, 12). Si 5 es mayor a 2, devuelve 15 sino devuelve 12', 
            'ES_MAYOR' => 'Evalua si un número es mayor a otro, si cumple la condición devuelve 1 (verdadero) sino devuelve 0 (falso). Ej: ES_MAYOR(5,2), devuelve 1',  
            'ES_MENOR' => 'Evalua si un número es menor a otro, si cumple la condición devuelve 1 (verdadero), sino devuelve 0 (falso). Ej: ES_MENOR(2,5), devuelve 1',      
            'ES_IGUAL' => 'Evalua si un número es igual a otro, si cumple la condición devuelve 1 (verdadero), sino devuelve 0 (falso). Ej: ES_IGUAL(3,3), devuelve 1', 
            'ES_DISTINTO' => 'Evalua si un número es distinto a otro, si cumple la condición  devuelve 1 (verdadero), sino devuelve 0 (falso). Ej: ES_DISTINTO(2,4), devuelve 1',          
            'ES_MAYOR_IGUAL' => 'Evalua si un número es mayor o igual a otro, si cumple la condición devuelve 1 (verdadero), sino devuelve 0 (falso). Ej: ES_MAYOR_IGUAL(5,2), devuelve 1',  
            'ES_MENOR_IGUAL' => 'Evalua si un número es menor o igual a otro, si cumple la condición  devuelve 1 (verdadero), sino devuelve 0 (falso). Ej: ES_MENOR_IGUAL(2,5), devuelve 1',         
            'COUNT' => "Devuelve cantidad de parámetros ingresados. Ej COUNT(4,5,6), devuelve 3",       
            'MAXIMO' => "Busca el máximo entre 2 o mas valores. Ej: MAXIMO(1,2,3), devuelve 3",   
            'MINIMO' => "Busca el mínimo entre 2 o mas valores. Ej: MINIMO(1,2,3), devuelve 1",
            'SUMATORIA' => "Devuelve el resultado de la sumatoria entre varios valores ingresados por parámetro",   
            'PERSONALIZADO' => 'Se utiliza para realizar varios cálculos combinados, los mismos deben estar separados en 2 grupos. Ej: PERSONALIZADO(( ( (5*3) + MINIMO(1,2,3)) / 4) - 1), devuelve 3',
            'EXPONENCIAL' => 'Calcula el exponencial de un número, en el primer parámetro se ingresa el número y en el segundo parámetro el exponente. Ej: EXPONENCIAL(5,2), devuelve 25',
            'RAIZ' => 'Calcula la raiz de un número, en el primer parámetro se ingresa el número y en el segundo parámetro la raiz. Ej: RAIZ(25,2), devuelve 5',
            'ABSOLUTO' => "Devuelve el número absoluto. Ej: ABSOLUTO(-5), devuelve 5",
            'AND' => "Devuelve 1 (verdadero) si todas las condiciones son verdaderas, caso contrario devuelve 0 (falso). Ej: AND( ES_MAYOR(5,2), ES_DISTINTO(4,2), ES_MENOR_IGUAL(1,3) )",
            'OR' => "Devuelve 1 (verdadero) alguna de las condiciones son verdaderas, caso contrario devuelve 0 (falso). Ej: OR( ES_MAYOR(5,2), ES_DISTINTO(4,2), ES_MENOR_IGUAL(1,3) )",
            'NOT' => "Si es verdadero devuelve falso, si es falso devuelve verdadero",
            'REDONDEO' => "Devuelve un número redondeado. Ej: REDONDEO(1.45), devuelve 1, Ej: REDONDEO(0.60), devuelve 1",
            'REDONDEO_ARRIBA' => "Devuelve un número redondeado hacia arriba. Ej: REDONDEO_ARRIBA(1.45), devuelve 2",
            'REDONDEO_ABAJO' => "Devuelve un número redondeado hacia abajo. Ej: REDONDEO_ABAJO(1.75), devuelve 1",
            'ALEATORIO' => "Devuelve un número de tipo entero aleatorio entre dos números. Ej: ALEATORIO(1,5)"
        );

        ksort($functions);
        return $functions;
    }

}
