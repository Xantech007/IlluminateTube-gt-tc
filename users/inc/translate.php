<!-- Google Translate -->
<div id="google_translate_element" style="margin-top: 8px;"></div>

<style>
/* Make container span full horizontal width */
#google_translate_element {
    width: 100% !important;
    display: block !important;
}

/* Force Google Translate inner wrapper and gadget to fill width */
#google_translate_element .goog-te-gadget,
#google_translate_element .goog-te-gadget-simple {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 8px 12px !important;
    background-color: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 8px !important;
}

/* Target inner select dropdown / text wrapper to expand */
#google_translate_element .goog-te-menu-value {
    width: 100% !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}

/* Force every child element and text span inside to be white */
#google_translate_element,
#google_translate_element *,
#google_translate_element .goog-te-gadget,
#google_translate_element .goog-te-gadget-simple,
#google_translate_element .goog-te-menu-value,
#google_translate_element .goog-te-menu-value span,
#google_translate_element a,
#google_translate_element span {
    color: #ffffff !important;
}

/* Turn arrow icon white */
#google_translate_element img {
    filter: brightness(0) invert(1) !important;
}

/* Ensure clean display if regular select drop-down renders */
#google_translate_element select.goog-te-combo {
    width: 100% !important;
    box-sizing: border-box !important;
    padding: 6px 10px !important;
    border-radius: 6px !important;
    background: #1f2937 !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}
</style>
<script type="text/javascript">
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'en,fr,es,de,pt,ar,zh-CN,ru', // Add or remove languages as needed
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, 'google_translate_element');
}
</script>
<script type="text/javascript" 
        src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit">
</script>
