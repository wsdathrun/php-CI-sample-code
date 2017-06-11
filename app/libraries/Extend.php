<?php
/**
 * Created by PhpStorm.
 * User: philips
 * Date: 16/12/7
 * Time: 下午5:50
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Extend
{
    function __construct()
    {
        $this->CI =& get_instance();

    }

    /**
     * 校验sku是否符合命名规则
     * @param $sku
     * @return bool
     */
    public function validateSku($sku)
    {
        $result = false;
        if(is_null($sku))
        {
            return $result;
        }

        // SKU长度在1-64之间,只能由数字、字母和符号“-”“_”组成
        $pregStr = "/^[0-9a-zA-Z-_]{1,64}$/";
        if(preg_match($pregStr,$sku))
        {
            $result = true;
        }
        return $result;
    }

    /**
     * 校验条形码是否符合命名规则
     * @param $barcode
     * @return bool
     */
    public function validateBarcode($barcode)
    {
        $result = false;
        if(is_null($barcode))
        {
            return $result;
        }

        // 条形长度在1-20之间,只能由数字组成
        $pregStr = "/^[0-9]{1,20}$/";
        if(preg_match($pregStr,$barcode))
        {
            $result = true;
        }
        return $result;
    }


    /**
     * 补充EAN13校验码
     * @param $digits
     * @return string
     */
    public function ean13CheckDigit($digits){
        //first change digits to a string so that we can access individual numbers
        $digits =(string)$digits;
        // 1. Add the values of the digits in the even-numbered positions: 2, 4, 6, etc.
        $even_sum = $digits{1} + $digits{3} + $digits{5} + $digits{7} + $digits{9} + $digits{11};
        // 2. Multiply this result by 3.
        $even_sum_three = $even_sum * 3;
        // 3. Add the values of the digits in the odd-numbered positions: 1, 3, 5, etc.
        $odd_sum = $digits{0} + $digits{2} + $digits{4} + $digits{6} + $digits{8} + $digits{10};
        // 4. Sum the results of steps 2 and 3.
        $total_sum = $even_sum_three + $odd_sum;
        // 5. The check character is the smallest number which, when added to the result in step 4,  produces a multiple of 10.
        $next_ten = (ceil($total_sum/10))*10;
        $check_digit = $next_ten - $total_sum;
        return $digits . $check_digit;
    }
}