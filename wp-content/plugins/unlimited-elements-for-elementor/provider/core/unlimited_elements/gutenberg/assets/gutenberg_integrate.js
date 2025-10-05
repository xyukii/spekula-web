var uelm_WidgetSettingsCache = [];
var uelm_WidgetSettingsCacheFlags = [];

(function (wp) {
	
	var g_debug = false;
	
	/**
	 * output some string
	 */
	function trace(str){
		console.log(str);
	}
	
	/**
	 * output text only if debug on
	 */
	function debug(str){
		
		if(g_debug == false)
			return(false);
		
		trace(str);
	}
	
	
	var wbe = wp.blockEditor;
	var wc = wp.components;
	var wd = wp.data;
	var we = wp.element;
	var el = we.createElement;
		
	// trigger block focus in case widget prevents clicks (carousels etc.)
	jQuery(document).on("click", ".ue-gutenberg-widget-wrapper", function () {
		jQuery(this).closest("[tabindex]").focus();
	});

	// prevent link clicks inside widgets
	jQuery(document).on("click", ".ue-gutenberg-widget-wrapper a", function (event) {
		event.preventDefault();
	});
	
	
	/**
	 * init in start
	 */
	function initInStart(){
	
		//add debug div
		jQuery(document).ready(function(){
			
			jQuery("body").append("<div id='div_debug' class='unite-div-debug'></div>");
			
		});
				
				
	}
	
	initInStart();
	
	var edit = function (props) {
		
		var previewUrl = props.attributes._preview;

		if (previewUrl)
			return el("img", { src: previewUrl, style: { width: "100%", height: "auto" } });

		var blockProps = wbe.useBlockProps();
		var widgetContentState = we.useState(null);
		var settingsVisibleState = we.useState(false);
		var settingsContentState = we.useState(null);

		var widgetRef = we.useRef(null);
		var widgetLoaderRef = we.useRef(null);
		var widgetRequestRef = we.useRef(null);

		var ucSettingsRef = we.useRef(new UniteSettingsUC());
		var ucHelperRef = we.useRef(new UniteCreatorHelper());

		var isEditorSidebarOpened = wd.useSelect(function (select) {
			return select("core/edit-post").isEditorSidebarOpened();
		});

		var activeGeneralSidebarName = wd.useSelect(function (select) {
			return select("core/edit-post").getActiveGeneralSidebarName();
		});

		var previewDeviceType = wd.useSelect((select) => {
			const editor = select(wp.editPost?.store || "core/edit-post");
			return editor.getDeviceType?.() || editor.__experimentalGetPreviewDeviceType?.() || "Desktop";
		}, []);

		var widgetId = "ue-gutenberg-widget-" + props.clientId;
		var settingsId = "ue-gutenberg-settings-" + props.clientId;
		var settingsTempId = settingsId + "-temp";
		var settingsErrorId = settingsId + "-error";

		var settingsVisible = settingsVisibleState[0];
		var setSettingsVisible = settingsVisibleState[1];

		var settingsContent = settingsContentState[0];
		var setSettingsContent = settingsContentState[1];

		var widgetContent = widgetContentState[0];
		var setWidgetContent = widgetContentState[1];

		var ucSettings = ucSettingsRef.current;
		var ucHelper = ucHelperRef.current;
		
		var initSettings = function () {
					
			ucSettings.destroy();

			var settingsElement = getSettingsElement();

			if (!settingsElement)
				return;
			
			ucSettings.init(settingsElement);
			ucSettings.setSelectorWrapperID(widgetId);
			ucSettings.setResponsiveType(previewDeviceType.toLowerCase());
			
			ucSettings.setEventOnChange(function () {
				
				saveSettings();
			});

			ucSettings.setEventOnSelectorsChange(function () {

				debug('setEventOnSelectorsChange');

				saveSettings();

				var css = ucSettings.getSelectorsCss();
				var includes = ucSettings.getSelectorsIncludes();

				jQuery(widgetRef.current).find("[name=uc_selectors_css]").text(css);

				if (includes) {
					var windowElement = getPreviewWindowElement();

					ucHelper.putIncludes(windowElement, includes);
				}
			});

			ucSettings.setEventOnResponsiveTypeChange(function (event, type) {

				debug('setEventOnResponsiveTypeChange: ' + props.attributes._id);

				uelm_WidgetSettingsCacheFlags[props.attributes._id] = true;
				uelm_WidgetSettingsCacheFlags[props.attributes._id + '_settings'] = true;
				
				var deviceType = type.charAt(0).toUpperCase() + type.substring(1);
				
				const editorStore = wp.editPost?.store || "core/edit-post";
				const dispatcher = wp.data.dispatch(editorStore);

				if (typeof dispatcher.setDeviceType === "function") {
					// WordPress >= 6.5
					dispatcher.setDeviceType(deviceType);
				} else if (typeof wp.data.dispatch("core/edit-post").__experimentalSetPreviewDeviceType === "function") {
					// WordPress < 6.5
					wp.data.dispatch("core/edit-post").__experimentalSetPreviewDeviceType(deviceType);
				}
			});

			// restore current settings, otherwise apply current
			var values = getSettings();

			if (values !== null)
				ucSettings.setValues(values);
			else
				saveSettings();
		};

		var getSettings = function () {
			
			try {
				return props.attributes.data ? JSON.parse(props.attributes.data) : null;
			} catch (e) {
				return null;
			}

		};

		var saveSettings = function () {
			props.setAttributes({
				_rootId: ucHelper.getRandomString(5),
				data: JSON.stringify(ucSettings.getSettingsValues()),
			});
		};

		var getSettingsElement = function () {
			
			if (!settingsContent)
				return;

			var settingsElement = jQuery("#" + settingsId);
			var settingsTempElement = jQuery("#" + settingsTempId);

			settingsTempElement.remove();

			if (settingsElement.length)
				return settingsElement;

			settingsTempElement = jQuery("<div id='" + settingsTempId + "' />")
				.hide()
				.html(settingsContent)
				.appendTo("body");

			return settingsTempElement;
		};

		var getPreviewWindowElement = function () {
			return window.frames["editor-canvas"] || window;
		};

		var loadSettingsContent = function () {

			var widgetCacheKey = props.attributes._id + '_settings'; 

			debug('loadSettingsContent: ' + widgetCacheKey);
			debug(props);

			/*
				for (var index in g_gutenbergParsedBlocks) {
					var block = g_gutenbergParsedBlocks[index];

					if (block._rootId === props.attributes._rootId) {
						setWidgetContent(block.html);
						
						delete g_gutenbergParsedBlocks[index];

						return;
					}
				}
*/

			if ( uelm_WidgetSettingsCache[widgetCacheKey] && uelm_WidgetSettingsCacheFlags[widgetCacheKey] ) {

				debug('init settings from cache');

				uelm_WidgetSettingsCacheFlags[widgetCacheKey] = false;
				setSettingsContent( uelm_WidgetSettingsCache[widgetCacheKey] );
				return;
			}

			g_ucAdmin.setErrorMessageID(settingsErrorId);

			const urlParams = new URLSearchParams(window.location.search);
			const isTestFreeVersion = urlParams.get("testfreeversion") === "true";

			var requestData = {
				id: props.attributes._id,
				config: getSettings(),
				platform: "gutenberg",
				source: "editor"
			};

			if (isTestFreeVersion) {
				requestData.testfreeversion = true;
			} 

			g_ucAdmin.ajaxRequest("get_addon_settings_html", requestData, function (response) {
				var html = g_ucAdmin.getVal(response, "html");
				
				debug('save widget settings cache: ' + widgetCacheKey);

				uelm_WidgetSettingsCache[widgetCacheKey] = html;
				uelm_WidgetSettingsCacheFlags[widgetCacheKey] = true;
				setSettingsContent(html);
			});
		};

		var loadWidgetContent = function () {

    var widgetCacheKey = props.attributes._id; 

    if ( uelm_WidgetSettingsCache[widgetCacheKey] && uelm_WidgetSettingsCacheFlags[widgetCacheKey] ) {
        debug('init widget from cache');
        
        uelm_WidgetSettingsCacheFlags[widgetCacheKey] = false;
        
        initWidget( uelm_WidgetSettingsCache[widgetCacheKey] );
        
        return;
    } else {
        debug(uelm_WidgetSettingsCache);
    }
    
    if (!widgetContent) {
        // load existing widgets from the page
        for (var index in g_gutenbergParsedBlocks) {
            var block = g_gutenbergParsedBlocks[index];

            if (block.name === props.name) {
                setWidgetContent(block.html);
                
                delete g_gutenbergParsedBlocks[index];

                return;
            }
        }
    }

    // ⬇Before there was: if (!settings) return; — REMOVE IT
    var settings = getSettings(); // may be null for a new block

    if (widgetRequestRef.current !== null)
        widgetRequestRef.current.abort();

    var loaderElement = jQuery(widgetLoaderRef.current);

    loaderElement.show();
    
    widgetRequestRef.current = g_ucAdmin.ajaxRequest("get_addon_output_data", {
        id: props.attributes._id,
        root_id: props.attributes._rootId,
        platform: "gutenberg",
        source: "editor",
        settings: settings || null,  // important: send null if there are no settings
        selectors: true,
    }, function (response) {
        debug('save widget cache: ' + widgetCacheKey);
        uelm_WidgetSettingsCache[widgetCacheKey] = response;
        uelm_WidgetSettingsCacheFlags[widgetCacheKey] = true;
        initWidget(response);
    }).always(function () {
        loaderElement.hide();
    });
};

		var initWidget = function (response) {
			var html = g_ucAdmin.getVal(response, "html");
			var includes = g_ucAdmin.getVal(response, "includes");
			var windowElement = getPreviewWindowElement();
			
			ucHelper.putIncludes(windowElement, includes, function () {
				setWidgetContent(html);
			});
		};

we.useEffect(function () {
    // ⬇️ FIRST output the widget with defaults
    loadWidgetContent();

    // remove loaded styles from the page
    jQuery("#unlimited-elements-styles").remove();
    
    return function () {
        // destroy the settings on the block unmount
        ucSettings.destroy();
    };
}, []);

// When the widget’s HTML appears — load the settings if they aren’t loaded yet
we.useEffect(function () {
    if (!widgetContent) return;

    // Insert the HTML manually (as you had it before)
    jQuery(widgetRef.current).html(widgetContent);

    // If settingsContent is still missing — load it
    if (!settingsContent) {
        loadSettingsContent();
    }
}, [widgetContent]);

		we.useEffect(function () {
			// settings are visible if:
			// - the block is selected
			// - the sidebar is opened
			// - the "block" tab is selected
			setSettingsVisible(
				props.isSelected
				&& isEditorSidebarOpened
				&& activeGeneralSidebarName === "edit-post/block"
			);
		}, [props.isSelected, isEditorSidebarOpened, activeGeneralSidebarName]);

		we.useEffect(function () {
			if (ucSettings.isInited())
				ucSettings.setResponsiveType(previewDeviceType.toLowerCase());
		}, [previewDeviceType]);

		we.useEffect(function () {
			if (!settingsVisible)
				return;

			initSettings();
		}, [settingsVisible]);

		we.useEffect(function () {
			if (!settingsContent)
				return;

			initSettings();
		}, [settingsContent]);

		we.useEffect(function () {
			loadWidgetContent();
		}, [props.attributes.data]);

		var settings = el(
			wbe.InspectorControls, {},
			el("div", { className: "ue-gutenberg-settings-error", id: settingsErrorId }),
			settingsContent && el("div", { id: settingsId, dangerouslySetInnerHTML: { __html: settingsContent } }),
			!settingsContent && el("div", { className: "ue-gutenberg-settings-spinner" }, el(wc.Spinner)),
		);

		var widget = el(
			"div", { className: "ue-gutenberg-widget-wrapper" },
			widgetContent && el("div", { className: "ue-gutenberg-widget-content", id: widgetId, ref: widgetRef }),
			widgetContent && el("div", { className: "ue-gutenberg-widget-loader", ref: widgetLoaderRef }, el(wc.Spinner)),
			!widgetContent && el("div", { className: "ue-gutenberg-widget-placeholder" }, el(wc.Spinner)),
		);

		return el("div", blockProps, settings, widget);
	};

	for (var name in g_gutenbergBlocks) {
		var block = g_gutenbergBlocks[name];
		var args = jQuery.extend(block, { edit: edit });

		// convert the svg icon to element
		if (typeof args.icon === 'string' && args.icon.trim().startsWith('<svg')) {
			try {
				const sanitized = args.icon.trim();
				args.icon = el('span', { dangerouslySetInnerHTML: { __html: sanitized } });
			} catch (e) {
				args.icon = '';
			}
		}
		
		wp.blocks.registerBlockType(name, args);
	}
})(wp);
