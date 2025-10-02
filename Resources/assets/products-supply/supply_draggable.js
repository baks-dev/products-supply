/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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
 *
 */

var containers = document.querySelectorAll(".draggable-zone");

// Массив для хранения выбранных заказов
let selectedOrders = new Set();

let status = null;

// @TODO фильтр
//const form = document.forms.order_delivery_filter_form;

//form.addEventListener("change", () =>
//{
//    setTimeout(() =>
//    {
//        form.submit();
//    }, 300);
//});

function getToken(url, ctx)
{


    return new Promise((resolve, reject) =>
    {
        fetch(url, {
            method: "POST",
            headers: new Headers({"Content-Type": "application/json"}),
            body: JSON.stringify(ctx),
        }).then(res =>
        {
            if(!res.ok)
            {
                throw new Error(`Unexpected status code ${res.status}`);
            }
            return res.json();
        }).then(data =>
        {
            resolve(data.token);

        }).catch(err =>
        {
            reject(err);
        });
    });
}

executeFunc(function P8X1I2diQ4()
{

    if(typeof Droppable !== "object" || typeof bootstrap !== "object")
    {
        return false;
    }

    const modal = document.getElementById("modal");
    const modal_bootstrap = bootstrap.Modal.getOrCreateInstance(modal);

    let droppable;

    // Define draggable element variable for permissions level
    let droppableOrigin;
    let droppableLevel;
    let droppableRestrict;

    let toDroppable;
    let isDraggingSelected = false;
    let draggedOrderIds = [];


    function initializeDroppable()
    {
        if(droppable)
        {
            droppable.destroy();
        }

        containers = document.querySelectorAll(".draggable-zone");

        droppable = new Droppable.default(containers, {
            draggable: ".draggable",
            dropzone: ".draggable-zone",
            handle: ".draggable .draggable-handle",
            mirror: {
                appendTo: "body",
                //constrainDimensions : true,
            },
        });

        droppable.on("drag:over:container", (e) =>
        {
            let status = e.overContainer.dataset.status;

            if(droppableLevel !== status)
            {
                droppableLevel = status;
            }
        });

        // Handle drag start event -- more info: https://shopify.github.io/draggable/docs/class/src/Draggable/DragEvent/DragEvent.js~DragEvent.html
        droppable.on("drag:start", (e) =>
        {
            document.body.style.overflow = "hidden";

            const draggedOrderId = e.originalSource.id;


            // Проверяем, является ли перетаскиваемый элемент частью выделенных
            if(selectedOrders.has(draggedOrderId) && selectedOrders.size > 1)
            {
                isDraggingSelected = true;
                draggedOrderIds = Array.from(selectedOrders);

                // Добавляем индикатор множественного перетаскивания
                const indicator = createMultipleDragIndicator(selectedOrders.size);
                e.originalSource.appendChild(indicator);


                // Делаем все выбранные элементы полупрозрачными во время перетаскивания
                selectedOrders.forEach(supplyId =>
                {
                    const element = document.getElementById(supplyId);

                    if(element && element !== e.originalSource)
                    {
                        /** При перетаскивании скрываем остальные перетаскиваемые элементы кроме текущего */
                        if(element.id !== draggedOrderId)
                        {
                            element.classList.add("d-none");
                        }
                    }
                });
            } else
            {
                isDraggingSelected = false;
                draggedOrderIds = [draggedOrderId];

                document.querySelectorAll(".draggable").forEach(draggable =>
                {
                    if(draggable.id !== draggedOrderId)
                    {
                        draggable.classList.add("opacity-50"); // полупрозрачный заказ
                    } else
                    {
                        // перетаскиваемуму заказа присваиваем высокий индекс позиционирования
                        draggable.classList.replace("z-0", "z-2");
                    }

                });
            }

        });


        // Handle drag over event -- more info: https://shopify.github.io/draggable/docs/class/src/Draggable/DragEvent/DragEvent.js~DragOverEvent.html
        droppable.on("drag:over", (e) =>
        {
            droppableLevel = e.overContainer.getAttribute("data-status");
        });

        // Handle drag stop event -- more info: https://shopify.github.io/draggable/docs/class/src/Draggable/DragEvent/DragEvent.js~DragStopEvent.html
        droppable.on("drag:stop", async (e) =>
        {
            // Удаляем индикатор множественного перетаскивания
            const indicator = e.originalSource.querySelector(".multiple-drag-indicator");

            if(indicator)
            {
                indicator.remove();
            }

            // Возвращаем нормальную прозрачность всем элементам
            document.querySelectorAll(".draggable").forEach(draggable =>
            {
                draggable.classList.remove("opacity-50"); // полупрозрачный заказ
                draggable.classList.replace("z-2", "z-0");
            });

            containers.forEach(c =>
            {
                c.classList.remove("draggable-dropzone--occupied");
            });

            document.body.style.overflow = "auto";

            let sourceLevel = e.sourceContainer.dataset.status;

            if(sourceLevel !== droppableLevel && droppableRestrict !== "restricted")
            {
                // Универсальная логика для одиночного и группового перетаскивания
                let supplysToProcess = [];

                if(isDraggingSelected && draggedOrderIds.length > 1)
                {
                    // Групповое перетаскивание
                    supplysToProcess = draggedOrderIds;
                } else
                {
                    // Одиночное перетаскивание
                    supplysToProcess = [e.originalSource.id];
                }

                console.log(`Из статуса ${sourceLevel} в статус ${droppableLevel}`);

                /** Включаем preload */
                modal.innerHTML = "<div class=\"modal-dialog modal-dialog-centered\"><div class=\"d-flex justify-content-center w-100\"><div class=\"spinner-border text-light\" role=\"status\"><span class=\"visually-hidden\">Loading...</span></div></div></div>";
                modal_bootstrap.show();

                // Единый запрос для всех заказов
                try
                {
                    let formData = new FormData();

                    supplysToProcess.forEach((id, index) =>
                    {
                        formData.append(`${droppableLevel}_product_supply_form[supplys][${index}][id]`, id);
                    });

                    const url = "/admin/products/supply/" + droppableLevel;

                    console.log(`Вызов ${url}`);

                    /** Формируем запрос на форму по статусу */
                    const response = await fetch("/admin/products/supply/" + droppableLevel, {
                        method: "POST",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        body: formData,
                    });

                    /** Если формы не отприсовалась - Изменение статуса без вызова формы */
                    if(response.status === 302 || response.status === 404)
                    {

                        // @TODO Нет реализации изменения статуса без формы
                        let $dangerOrderToast = "{ \"type\":\"danger\" , " +
                            "\"header\":\"Изменение статуса\"  , " +
                            "\"message\" : \"Невозможно изменить статус\" }";
                        createToast(JSON.parse($dangerOrderToast));
                        return;

                        //formData = new FormData();
                        //
                        //supplysToProcess.forEach((id, index) =>
                        //{
                        //    formData.append(`status_form[orders][${index}][id]`, id);
                        //});
                        //
                        ///** Запрос на изменение статуса */
                        //await fetch("/admin/products/supply/status/" + droppableLevel, {
                        //    method: "POST",
                        //    headers: {
                        //        "X-Requested-With": "XMLHttpRequest",
                        //    },
                        //    body: formData,
                        //});
                        //
                        //// Очищаем список выбранных заказов и закрываем модал
                        //selectedOrders.clear();
                        //updateSelectedOrdersVisuals();
                        //modal_bootstrap.hide();
                        //
                        //createToast(JSON.parse(
                        //    "{ \"type\":\"success\" , " +
                        //    "\"header\" : \"Обновление заказов\"  , " +
                        //    "\"message\" : \"Статус заказов успешно обновлен\" }",
                        //));
                        //
                        //return;
                    }

                    /** Отрисовка формы */
                    if(response.status === 200)
                    {
                        const result = await response.text();

                        // Очищаем список выбранных заказов
                        selectedOrders.clear();
                        updateSelectedOrdersVisuals();

                        // Только один заказ требует форму - показываем её
                        modal.innerHTML = result;

                        /** Инициируем LAZYLOAD */
                        let lazy = document.createElement("script");
                        lazy.src = "/assets/" + $version + "/js/lazyload.min.js";
                        document.head.appendChild(lazy);

                    } else
                    {
                        throw new Error(`Unexpected status code ${response.status}`);
                    }

                } catch(error)
                {
                    modal_bootstrap.hide();
                    selectedOrders.clear();
                    updateSelectedOrdersVisuals();
                    console.error("Ошибка обновления:", error);

                    let $dangerOrderToast = "{ \"type\":\"danger\" , " +
                        "\"header\":\"Ошибка сети\"  , " +
                        "\"message\" : \"Ошибка при отправке запроса на сервер!\" }";
                    createToast(JSON.parse($dangerOrderToast));
                }
            }

            // Сбрасываем флаги
            isDraggingSelected = false;
            draggedOrderIds = [];

            /* Re-initialize Droppable to ensure it's aware of any DOM changes */
            setTimeout(initializeDroppable, 0);
        });

        // Handle drop event -- https://shopify.github.io/draggable/docs/class/src/Droppable/DroppableEvent/DroppableEvent.js~DroppableDroppedEvent.html
        droppable.on("droppable:dropped", (e) =>
        {
            droppableRestrict = e.dropzone.dataset.level;

            if(droppableRestrict === "restricted")
            {
                e.cancel();
            }
        });
    }


    // Добавляем обработчики для чекбоксов
    function initCheckboxHandlers()
    {
        const all = document.getElementById("check-all");

        const checkboxes = document.querySelectorAll(".draggable input[type=\"checkbox\"]");

        checkboxes.forEach(checkbox =>
        {

            /** снимаем чеки при обновлении */
            checkbox.checked = false;

            checkbox.addEventListener("change", function()
            {
                const supplyId = this.closest(".draggable").id;
                const draggableElement = this.closest(".draggable");

                /** Ограничиваем выделяемые заказы по статусу */
                status = checkbox.dataset.status;

                if(this.checked)
                {
                    //selectCheckboxesByPrefix(checkbox.id);

                    selectedOrders.add(supplyId);
                    draggableElement.classList.add("selected-order");

                    /** Выделяем аналогичные заказы */

                    //// Извлекаем префикс (убираем последнюю часть после последнего дефиса)
                    //let checkboxId = checkbox.id;
                    //const idParts = checkboxId.split("-");
                    //idParts.pop(); // Удаляем последнюю часть
                    //const prefix = idParts.join("-");
                    //
                    //// Находим и выделяем все соответствующие checkbox
                    //const checkboxes = document.querySelectorAll(`input[type="checkbox"][id^="${prefix}"]`);
                    //
                    //checkboxes.forEach(check =>
                    //{
                    //    if(!check.checked)
                    //    {
                    //        check.checked = true;
                    //        selectedOrders.add(check.value);
                    //    }
                    //});

                } else
                {
                    selectedOrders.delete(supplyId);
                    draggableElement.classList.remove("selected-order");
                }

                // Визуальное выделение выбранных карточек
                updateSelectedOrdersVisuals();
            });
        });

        if(all)
        {
            /** снимаем чек при обновлении */
            all.checked = false;

            all.addEventListener("change", function(all)
            {
                checkboxes.forEach(checkbox =>
                {

                    if(checkbox.dataset.status === "new")
                    {
                        const supplyId = checkbox.closest(".draggable").id;
                        const draggableElement = checkbox.closest(".draggable");

                        checkbox.checked = this.checked;

                        if(this.checked)
                        {
                            selectedOrders.add(supplyId);
                            draggableElement.classList.add("selected-order");

                        } else
                        {
                            selectedOrders.delete(supplyId);
                            draggableElement.classList.remove("selected-order");
                        }
                    }

                });

                updateSelectedOrdersVisuals();
            });
        }

    }

    // Функция для обновления визуального состояния выбранных карточек
    function updateSelectedOrdersVisuals()
    {
        const allDraggables = document.querySelectorAll(".draggable");

        allDraggables.forEach(draggable =>
        {
            const supplyId = draggable.id;
            const draggableHandle = draggable.querySelector(".draggable-handle");
            const draggableCheckbox = draggable.querySelector("input[type=\"checkbox\"]");


            if(selectedOrders.has(supplyId))
            {
                /** Показать полностью весь заказ */
                draggable.classList.remove("opacity-50");
                draggable.classList.replace("z-0", "z-2");

                //draggable.classList.add("opacity-100");

                /** Выделяем заказ рамкой */
                draggable.style.transform = "scale(0.98)";
                draggable.style.boxShadow = "0 0 0 2px #007bff";

                // Если есть выделенные карточки, включаем перетаскивание только для них
                if(draggableHandle)
                {
                    draggableHandle.style.pointerEvents = "auto";
                }
            } else
            {

                draggable.removeAttribute("style");

                // Если есть выделенные карточки, отключаем перетаскивание для невыделенных
                if(draggableHandle)
                {
                    if(selectedOrders.size > 0)
                    {
                        draggable.classList.add("opacity-50"); // полупрозрачный заказ

                        draggableHandle.style.pointerEvents = "none";

                        /** получаем элемент chekbox */
                        if(draggableCheckbox && draggableCheckbox.dataset.status !== status)
                        {
                            draggableCheckbox.disabled = true;
                        }
                    }

                    if(selectedOrders.size === 0)
                    {
                        // Если нет выделенных карточек, включаем перетаскивание для всех
                        draggable.classList.remove("opacity-50");

                        draggableHandle.style.pointerEvents = "auto";
                        draggableCheckbox ? draggableCheckbox.disabled = false : false;

                        status = null;
                    }

                }

            }
        });
    }

    // Функция для создания визуального индикатора множественного перетаскивания
    function createMultipleDragIndicator(count)
    {
        const indicator = document.createElement("div");
        indicator.className = "multiple-drag-indicator";
        indicator.style.cssText = `
            position: absolute;
            top: -10px;
            right: -10px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            z-index: 1000;
        `;
        indicator.textContent = count;
        return indicator;
    }

    // Инициализируем обработчики чекбоксов
    initCheckboxHandlers();

    // Initial call to set up Droppable
    initializeDroppable();

    return true;
});