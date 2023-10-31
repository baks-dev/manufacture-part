/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

previousValue = null;
productTotal = document.getElementById('manufacture_part_products_form_total');

if (productTotal)
{

    productTotal.addEventListener('focus', resetValue, false);
    productTotal.addEventListener('blur', restoreValue, false);

    function resetValue() {
        previousValue = productTotal.value;
        productTotal.value = '';
    }

    function restoreValue() {
        if (productTotal.value === '') {
            productTotal.value = previousValue;
        }
    }


    /** Событие на изменение количество в ручную */
    productTotal.addEventListener('input', orderModalCounter.debounce(300));

    /** Счетчик  */
    document.querySelector('#plus').addEventListener('click', () => {

        let price_total = productTotal;
        let result = price_total.value * 1;
        let max = price_total.dataset.max * 1;

        if (result < max) {
            result = result + 1;
            productTotal.value = result;
        }
    });


    document.querySelector('#minus').addEventListener('click', () => {
        let price_total = productTotal;
        let result = price_total.value * 1;

        if (result > 1) {
            result = result - 1
            productTotal.value = result;
        }
    });


    function orderModalCounter() {

        let result = this.value * 1;
        let max = this.dataset.max * 1;

        if (isNaN(result) || result === 0) {
            productTotal.value = 1;
            result = 1;
        }

        if (result > max) {
            productTotal.value = max;
            result = max;
        }
    }

}

