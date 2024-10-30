var __ = wp.i18n.__;

var el = wp.element.createElement,
    registerBlockType = wp.blocks.registerBlockType,
    ServerSideRender = wp.components.ServerSideRender,
    SelectControl = wp.components.SelectControl,
    TextControl = wp.components.TextControl,
    blockStyle = { backgroundColor: '#900', color: '#fff', padding: '20px' };
    
var {
	PluginSidebar,
	PluginSidebarMoreMenuItem,
	PluginPostStatusInfo,
	Panel,
	PanelBody,
	PanelRow,
	InspectorControls,
	BlockControls,
	TextareaControl,
	RichText
} = wp.editor;	

var { Fragment } = wp.element;

/* Custom plugin icon */
const customIcon = el('svg', 
{ 
	width: 20, 
	height: 20,
	viewBox: "0 0 120 120",
	class: "dashicon dashicons-admin-generic",
	xmlns: "http://www.w3.org/2000/svg"
},
el( 'path', { d: 'M5 30 A5,5 0 0 1 10,25 H 85 A5,5 0 0 1 90,30 V 115 A5,5 0 0 1 85,120 H 10 A5,5 0 0 1 5,115 Z', fill: 'white',	stroke: 'black', strokeWidth: '6'}),
el( 'path', { d: 'M25 10 A5,5 0 0 1 30,5 H 110 A5,5 0 0 1 115,10 V 95 A5,5 0 0 1 110,100 H 30 A5,5 0 0 1 25,95 Z', fill: 'white',	stroke: 'black', strokeWidth: '6'}),
el( 'circle', { cx: '95', cy: '25', r: 7, fill: 'black',	stroke: 'black'}),
el( 'circle', { cx: '70', cy: '52.5', r: '20', fill: 'white',	stroke: 'black', strokeWidth: '6'}),
);

/* Registering block type */
registerBlockType( 'insta-grid/insta-grid', {
    title: __('Instagram Grid', 'insta-grid'),
    icon: customIcon,
    category: 'widgets',
    
    /* Attributes used by block */
    attributes: {
		cols: {
			type: 'string',
			default: "3",
		},
		rows: {
			type: 'string',
			default: "3",
		},
		width: {
			type: 'string',
			default: "100%",
		},
		align: {
			type: 'string',
			default: "center",
		},
	},
	
	/* Block interface - editor side */
    edit: function(props) {
		return [el('div', {},
			el(ServerSideRender, {
				block: props.name,
				attributes: props.attributes
			})), 
			el(InspectorControls, {key: 'inspector'},
				el(SelectControl, {
					label: __('Align', 'insta-grid'), 
					id: 'insta_grid_align',
					name: "align",
					defaultValue: props.attributes.align, 
					options: [
						{ label: __('Left', 'insta-grid'), value: 'left'},
						{ label: __('Center', 'insta-grid'), value: 'center'},
						{ label: __('Right', 'insta-grid'), value: 'right'},
					],
					onChange: (value)=>{ if(value!='') props.setAttributes({align: value}); }
				}),
				el(TextControl,
                    {
                        label: __('Width', 'insta-grid'), 
                        id: 'insta_grid_width',
                        name: "width",
                        defaultValue: props.attributes.width,
                        //instanceId: 'gravity-form-selector',
                        placeholder: 'Ex: 100%',
                        onChange: function (value) {
                            if(value!='') props.setAttributes({width: value});
                        }
                    }
				),
				el(TextControl,
                    {
                        label: __('Rows', 'insta-grid'), 
                        id: 'insta_grid_rows',
                        name: "rows",
                        defaultValue: props.attributes.rows,
                        //instanceId: 'gravity-form-selector',
                        //placeholder: 'Ex: 100%',
                        onChange: function (value) {
                            if(value!='') props.setAttributes({rows: value});
                        }
                    }
				),
				el(TextControl,
                    {
                        label: __('Cols', 'insta-grid'), 
                        id: 'insta_grid_cols',
                        name: "cols",
                        defaultValue: props.attributes.cols,
                        //instanceId: 'gravity-form-selector',
                        //placeholder: 'Ex: 100%',
                        onChange: function (value) {
                        	if(value!='') props.setAttributes({cols: value});
                        }
                    }
				),
				//el("span", {}, "px" ),
			)];
    },

	/* Block visualization for the front-end */
    save: function(props) {
    	return [el(ServerSideRender, {
				block: props.name,
				attributes: props.attributes
			})];
    },
} );

