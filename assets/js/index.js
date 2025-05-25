const CP_settings = window.wc.wcSettings.getSetting( 'WC_COINPAY_data', {} );
const CP_label = window.wp.htmlEntities.decodeEntities( CP_settings.title ) || '';

const CP_Content = () => {
    return window.wp.htmlEntities.decodeEntities( CP_settings.description || '' );
};

const CP_Icon = () => {
    return CP_settings.icon
        ? React.createElement('img', { src: CP_settings.icon, style: { marginLeft: '20px' } })
        : null;
}

const CP_Label = () => {
    return React.createElement(
        'span',
        { style: { width: '100%', display: 'flex', gap: '5px' } },
        CP_label,
        React.createElement(CP_Icon)
    );
}


const CP_Block_Gateway = {
    name: 'WC_COINPAY',
    label: React.createElement(CP_Label),
    content: React.createElement(CP_Content),
    edit: React.createElement(CP_Content),
    canMakePayment: () => true,
    ariaLabel: CP_label,
    supports: {
        features: CP_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( CP_Block_Gateway );