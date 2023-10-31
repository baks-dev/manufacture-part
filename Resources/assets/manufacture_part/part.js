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


manufacture_part_category = document.getElementById('manufacture_part_form_category');

if (manufacture_part_category) {
    manufacture_part_category.addEventListener('change', changeObjectCategory, false);
}


function changeObjectCategory() {

    let replaceId = 'manufacture_part_form_action';
    let incomingForm = document.forms.manufacture_part_form;


    document.getElementById(replaceId).disable = true;


    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(incomingForm.getAttribute('method'), incomingForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function () {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if (requestModalName.readyState === 4 && requestModalName.status === 200) {

            let result = requestModalName.response.getElementById(replaceId);

            document.getElementById(replaceId).replaceWith(result);

            /* Удаляем предыдущий Select2 */
            let select2 = document.getElementById(replaceId + '_select2');

            if (select2) {
                select2.remove();
            }



            let replacer = document.getElementById(replaceId);

            if (replacer.tagName === 'SELECT') {
                new NiceSelect(replacer, {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение торгового предложения */
                //let offerChange = document.getElementById('incoming_product_stock_form_preOffer');

                // if (offerChange) {
                //     offerChange.addEventListener('change', changeObjectOffer, false);
                // }
            }


        }

        return false;
    });

    requestModalName.send(formData);
}

