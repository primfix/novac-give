(() => {
    let settings = {};

    // Render gateway fields
    function NovacGiveWPFields() {
        return window.wp.element.createElement(
            "div",
            {
                className: 'novac-givewp-help-text'
            },
            window.wp.element.createElement(
                "p",
                {
                    style: {marginBottom: 0}
                },
                settings.message,
            )
        );
    }

    // Gateway object
    const NovacGiveWPGateway = {
        id: "novac",
        initialize() {
            settings = this.settings
        },
        Fields() {
            return window.wp.element.createElement(NovacGiveWPFields);
        },
    };

    // Register the gateway
    window.givewp.gateways.register(NovacGiveWPGateway);
})();