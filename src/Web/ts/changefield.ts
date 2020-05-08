declare var toastr: any;
declare var math: any;
declare var LA: any;
declare var BigNumber: any;
declare function swal(...x:any): any;

namespace Exment {
    export class ChangeFieldEvent {
        /**
         * Call only once. It's $(document).on event.
         */
        public static AddEventOnce() {
            $(document).on('change', '[data-changehtml]', {}, ChangeFieldEvent.changeHtml);
        }

        /**
         * Change html event
         * If select A(select2 item), change html object 
         * @param ev 
         */
        private static changeHtml = (ev) => {
            //
            const $target = $(ev.target);
            const val = $target.val();

            let ajax = $target.data('changehtml') as string;
            let $html = $($target.data('changehtml_target'));

            if(!hasValue(val)){
                $html.children().remove();
                return;
            }


            // get html
            // Please return this,
            // [
            //     'body' => (html),
            //     'script' => ([form script as array]),
            // ]
            $.ajax({
                url: ajax,
                type: "GET",
                data: {
                    'val': val,
                },
                success: function (data) {
                    if(hasValue(data.body)){
                        $html.children().remove();

                        // find target value
                        let $ajaxTarget = $(data.body).find($target.data('changehtml_response'));

                        // set html inner div
                        let $inner = $('<div data-changehtml_key="' + val + '" />');
                        $inner.append($ajaxTarget).appendTo($html);
                    }

                    if (hasValue(data.script)) {
                            eval(data.script);
                    }
                },
            });
        }

        /**
         * toggle right-top help link and color
         */
        public static ChangeFieldEvent(ajax, eventTriggerSelector, eventTargetSelector, replaceSearch, replaceWord, showConditionKey){
            if(!hasValue(ajax)){
                return;
            }
            $('.has-many-table').off('change').on('change', eventTriggerSelector, function (ev) {
                var changeTd = $(ev.target).closest('tr').find('.changefield-div');
                if(!hasValue($(ev.target).val())){
                    changeTd.html('');
                    return;
                }
                $.ajax({
                    url: ajax,
                    type: "GET",
                    data: {
                        'target': $(this).closest('tr').find(eventTargetSelector).val(),
                        'cond_name': $(this).attr('name'),
                        'cond_key': $(this).val(),
                        'replace_search': replaceSearch,
                        'replace_word': replaceWord,
                        'show_condition_key': showConditionKey,
                    },
                    context: this,
                    success: function (data) {
                        var json = JSON.parse(data);
                        $(this).closest('tr').find('.changefield-div').html(json.html);
                        if (json.script) {
                            eval(json.script);
                        }
                    },
                });
            });
        }
    }
}
$(function () {
    Exment.ChangeFieldEvent.AddEventOnce();
});
