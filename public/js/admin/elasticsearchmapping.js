/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

/*global Ext:false */
/*global elasticsearch:false */

Ext.namespace('elasticsearch');
Ext.namespace('Ext.ux.grid');
Ext.ux.grid.CheckColumn = Ext.extend(
    Ext.grid.Column, {
        processEvent: function (name, e, grid, rowIndex, colIndex) {
            'use strict';
            if (name === 'click') {
                var record = grid.store.getAt(rowIndex);
                record.set(this.dataIndex,
                    !record.data[this.dataIndex]);
                return false;
            } else {
                return Ext.ux.grid.CheckColumn.superclass.processEvent
                    .apply(this, arguments);
            }
        },
        renderer: function (v, p, record) {
            'use strict';
            p.css += ' x-grid3-check-col-td';
            return String.format('<div class="x-grid3-check-col{0}"> </div>', v ? '-on' : '');
        },
        init: Ext.emptyFn
    }
);
elasticsearch.init = function (record) {
    'use strict';
    var analyzers = [];

    if (typeof window.gc2Options.es_settings === "object") {
        analyzers.push([]);
        $.each(window.gc2Options.es_settings.settings.analysis.analyzer, function (i, v) {
            analyzers.push([i, i]);
        });
    } else {
        alert("No Elasticsearch settings");
    }
    elasticsearch.reader = new Ext.data.JsonReader({
        totalProperty: 'total',
        successProperty: 'success',
        idProperty: 'id',
        root: 'data',
        messageProperty: 'message'
    }, [
        {
            name: 'column',
            allowBlank: false
        },
        {
            name: 'type',
            allowBlank: false
        },
        {
            name: 'elasticsearchtype',
            allowBlank: true
        },
        {
            name: 'format',
            allowBlank: true
        },
        {
            name: 'index',
            allowBlank: true
        },
        {
            name: 'analyzer',
            allowBlank: true
        },
        {
            name: 'search_analyzer',
            allowBlank: true
        },
        {
            name: 'index_analyzer',
            allowBlank: true
        },
        {
            name: 'boost',
            allowBlank: true
        },
        {
            name: 'null_value',
            allowBlank: true
        },
        {
            name: 'fielddata',
            allowBlank: true
        }
    ]);

    elasticsearch.writer = new Ext.data.JsonWriter({
        writeAllFields: true,
        encode: false
    });
    elasticsearch.proxy = new Ext.data.HttpProxy(
        {
            restful: true,
            api: {
                read: '/controllers/layer/elasticsearch/' + record.get("f_table_schema") + '.' + record.get("f_table_name"),
                update: '/controllers/layer/elasticsearch/' + record.get("f_table_schema") + '.' + record.get("f_table_name") + '/' + record.get("_key_")
            },
            listeners: {
                write: function (store, action, result, transaction, rs) {
                    if (transaction.success) {
                        //elasticsearch.store.load();
                    }
                },
                exception: function (proxy, type, action, options, response, arg) {
                    if (response.status !== 200) {
                        Ext.MessageBox.show({
                            title: __('Failure'),
                            msg: __(Ext.decode(response.responseText).message),
                            buttons: Ext.MessageBox.OK,
                            width: 400,
                            height: 300,
                            icon: Ext.MessageBox.ERROR
                        });
                        elasticsearch.store.load();
                    }

                }
            }
        }
    );
    elasticsearch.store = new Ext.data.Store({
        writer: elasticsearch.writer,
        reader: elasticsearch.reader,
        proxy: elasticsearch.proxy,
        autoSave: true
    });

    elasticsearch.store.load();

    elasticsearch.grid = new Ext.grid.EditorGridPanel({
        iconCls: 'silk-grid',
        store: elasticsearch.store,
        // autoExpandColumn: "desc",
        height: 545,
        // width: 750,
        ddGroup: 'mygridDD',
        enableDragDrop: false,
        viewConfig: {
            forceFit: true
        },
        border: false,
        region: 'center',
        sm: new Ext.grid.RowSelectionModel({
            singleSelect: true
        }),
        cm: new Ext.grid.ColumnModel({
            defaults: {
                sortable: true,
                editor: {
                    xtype: "textfield"
                },
                menuDisabled: true
            },
            columns: [
                {
                    id: "column",
                    header: __("PG column"),
                    dataIndex: "column",
                    sortable: true,
                    editable: false,
                    width: 55,
                    tooltip: __("Name of the PostGreSQL column")
                },
                {
                    id: "type",
                    header: __("Native PG type"),
                    dataIndex: "type",
                    sortable: true,
                    width: 45,
                    editable: false,
                    tooltip: __("Type of the PostGreSQL column")
                },
                {
                    id: "elasticsearchtype",
                    header: __("Elasticsearch type"),
                    dataIndex: 'elasticsearchtype',
                    width: 55,
                    tooltip: __("Field type in Elasticsearch."),
                    editor: {
                        xtype: 'combo',
                        store: new Ext.data.ArrayStore({
                            fields: ['abbr', 'action'],
                            data: [
                                ['text', 'text'],
                                ['keyword', 'keyword'],
                                ['integer', 'integer'],
                                ['float', 'float'],
                                ['double', 'double'],
                                ['short', 'short'],
                                ['long', 'long'],
                                ['byte', 'byte'],
                                ['boolean', 'boolean'],
                                ['date', 'date'],
                                ['geo_point', 'geo_point'],
                                ['geo_shape', 'geo_shape'],
                                ['binary', 'binary'],
                                ['ip', 'ip'],
                                ['object', 'object']
                            ]
                        }),
                        displayField: 'action',
                        valueField: 'abbr',
                        mode: 'local',
                        typeAhead: false,
                        editable: false,
                        triggerAction: 'all'
                    }
                },
                {
                    id: "index",
                    header: __("Index"),
                    dataIndex: 'index',
                    width: 45,
                    tooltip: __("Set to analyzed for the field to be indexed and searchable after being broken down into token using an analyzer. not_analyzed means that its still searchable, but does not go through any analysis process or broken down into tokens. no means that it won’t be searchable at all. Defaults to analyzed."),
                    editor: {
                        xtype: 'combo',
                        store: new Ext.data.ArrayStore({
                            fields: ['abbr', 'action'],
                            data: [
                                ["",""],
                                ["analyzed", "analyzed"],
                                ["not_analyzed", "not_analyzed"],
                                ["no", "no"]
                            ]
                        }),
                        displayField: 'action',
                        valueField: 'abbr',
                        mode: 'local',
                        typeAhead: false,
                        editable: false,
                        triggerAction: 'all'
                    }
                },
                {
                    id: "analyzer",
                    header: __("Analyzer"),
                    dataIndex: 'analyzer',
                    width: 45,
                    tooltip: __("The analyzer used to analyze the text contents when analyzed during indexing and when searching using a query string. Defaults to the globally configured analyzer."),
                    editor: {
                        xtype: 'combo',
                        store: new Ext.data.ArrayStore({
                            fields: ['abbr', 'action'],
                            data: analyzers
                        }),
                        displayField: 'action',
                        valueField: 'abbr',
                        mode: 'local',
                        typeAhead: false,
                        editable: false,
                        triggerAction: 'all'
                    }
                },
                {
                    id: "searchanalyzer",
                    header: __("Search analyzer"),
                    dataIndex: 'search_analyzer',
                    width: 50,
                    tooltip: __("The analyzer used to analyze the text contents when analyzed during indexing."),
                    editor: {
                        xtype: 'combo',
                        store: new Ext.data.ArrayStore({
                            fields: ['abbr', 'action'],
                            data: analyzers
                        }),
                        displayField: 'action',
                        valueField: 'abbr',
                        mode: 'local',
                        typeAhead: false,
                        editable: false,
                        triggerAction: 'all'
                    }
                },
                {
                    id: "boost",
                    header: __("Boost"),
                    dataIndex: "boost",
                    sortable: true,
                    width: 35,
                    tooltip: __("The boost value. Defaults to 1.0."),
                    editor: new Ext.form.SpinnerField({
                        allowBlank: true
                    })
                },
                {
                    id: "null_value",
                    header: __("Null value"),
                    dataIndex: "null_value",
                    sortable: true,
                    width: 35,
                    tooltip: __("When there is a (JSON) null value for the field, use the null_value as the field value. Defaults to not adding the field at all."),
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                },
                {
                    id: "fielddata",
                    header: __("Fielddata"),
                    dataIndex: "fielddata",
                    sortable: true,
                    width: 35,
                    tooltip: __("Check if you try to sort, aggregate, or access values from a script on a text field. Fielddata can consume a LOT of heap space, especially when loading high cardinality text fields."),
                    xtype: 'checkcolumn'
                },
                {
                    id: "format",
                    header: __("Format"),
                    dataIndex: "format",
                    sortable: true,
                    width: 35,
                    tooltip: __("The date format. Defaults to dateOptionalTime."),
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                }
            ]
        }),
        tbar: [{
            text: '<i class="fa fa-search-plus"></i> ' + __("(Re)index in Elasticsearch"),
            id: "index-in-elasticsearch-btn",
            disabled: (window.gc2Options.esIndexingInGui) ? false : true,
            handler: function () {
                tableStructure.onIndexInElasticsearch(record);
            }
        },
            {
                text: '<i class="fa fa-search-minus"></i> ' + __("Delete from Elasticsearch"),
                id: "delete-from-elasticsearch-btn",
                disabled: window.gc2Options.esIndexingInGui ? record.data.indexed_in_es ? false : true : true,
                handler: function () {
                    tableStructure.onDeleteFromElasticsearch(record);
                }
            }]
    });
};