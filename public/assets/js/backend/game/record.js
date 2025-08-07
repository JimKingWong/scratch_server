define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'game/record/index' + location.search,
                    add_url: 'game/record/add',
                    edit_url: 'game/record/edit',
                    del_url: 'game/record/del',
                    multi_url: 'game/record/multi',
                    import_url: 'game/record/import',
                    table: 'game_record',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.name', title: __('User.name'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'cate_id', title: __('Cate_id')},
                        {field: 'roundid', title: __('Roundid'), operate: 'LIKE'},
                        {field: 'bei_amount', title: __('下注金额'), operate:'BETWEEN'},
                        {field: 'win_amount', title: __('Win_amount'), operate:'BETWEEN'},
                        {field: 'is_win', title: __('Is_win'), searchList: {"0":__('Is_win 0'),"1":__('Is_win 1')}, formatter: Table.api.formatter.normal},
                        // {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
