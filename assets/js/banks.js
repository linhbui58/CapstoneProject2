// Vietnamese Banks — logos from VietQR API (no CORS block)
// Format: https://img.vietqr.io/image/{BIN}-compact2.png
const VIETNAM_BANKS = [
    { code: 'VCB',   name: 'Vietcombank',       fullname: 'NH TMCP Ngoại thương Việt Nam',       color: '#006438', bin: '970436' },
    { code: 'VTB',   name: 'VietinBank',         fullname: 'NH TMCP Công thương Việt Nam',        color: '#c8000a', bin: '970415' },
    { code: 'BIDV',  name: 'BIDV',               fullname: 'NH TMCP Đầu tư và Phát triển VN',     color: '#1a56a0', bin: '970418' },
    { code: 'ACB',   name: 'ACB',                fullname: 'NH TMCP Á Châu',                      color: '#004a8e', bin: '970416' },
    { code: 'TCB',   name: 'Techcombank',         fullname: 'NH TMCP Kỹ thương Việt Nam',          color: '#e31f26', bin: '970407' },
    { code: 'MB',    name: 'MBBank',              fullname: 'NH TMCP Quân đội',                    color: '#9b0000', bin: '970422' },
    { code: 'VPB',   name: 'VPBank',              fullname: 'NH TMCP Việt Nam Thịnh Vượng',        color: '#007b40', bin: '970432' },
    { code: 'STB',   name: 'Sacombank',           fullname: 'NH TMCP Sài Gòn Thương Tín',          color: '#005bac', bin: '970403' },
    { code: 'TPB',   name: 'TPBank',              fullname: 'NH TMCP Tiên Phong',                  color: '#8b1a1a', bin: '970423' },
    { code: 'HDB',   name: 'HDBank',              fullname: 'NH TMCP Phát triển TP. HCM',          color: '#1a3e6c', bin: '970437' },
    { code: 'SCB',   name: 'SCB',                 fullname: 'NH TMCP Sài Gòn',                     color: '#b22222', bin: '970429' },
    { code: 'SHB',   name: 'SHB',                 fullname: 'NH TMCP Sài Gòn - Hà Nội',            color: '#c8000a', bin: '970443' },
    { code: 'EIB',   name: 'Eximbank',            fullname: 'NH TMCP Xuất Nhập khẩu VN',           color: '#004a8e', bin: '970431' },
    { code: 'MSB',   name: 'MSB',                 fullname: 'NH TMCP Hàng Hải Việt Nam',           color: '#e31f26', bin: '970426' },
    { code: 'OCB',   name: 'OCB',                 fullname: 'NH TMCP Phương Đông',                 color: '#005bac', bin: '970448' },
    { code: 'LPB',   name: 'LienVietPostBank',    fullname: 'NH TMCP Bưu điện Liên Việt',          color: '#0073be', bin: '970449' },
    { code: 'NAB',   name: 'Nam A Bank',          fullname: 'NH TMCP Nam Á',                       color: '#c00000', bin: '970428' },
    { code: 'SEAB',  name: 'SeABank',             fullname: 'NH TMCP Đông Nam Á',                  color: '#d4000d', bin: '970440' },
    { code: 'VIB',   name: 'VIB',                 fullname: 'NH TMCP Quốc tế Việt Nam',            color: '#005ca9', bin: '970441' },
    { code: 'ABB',   name: 'ABBank',              fullname: 'NH TMCP An Bình',                     color: '#e60026', bin: '970425' },
    { code: 'MOMO',  name: 'MoMo',                fullname: 'Ví điện tử MoMo',                     color: '#ae2070', bin: null },
    { code: 'ZALO',  name: 'ZaloPay',             fullname: 'Ví điện tử ZaloPay',                  color: '#0068ff', bin: null },
    { code: 'VNPAY', name: 'VNPAY',               fullname: 'Cổng thanh toán VNPAY',               color: '#005bac', bin: null },
    { code: 'VISA',  name: 'Visa',                fullname: 'Visa International',                  color: '#1a1f71', bin: null },
    { code: 'MC',    name: 'Mastercard',          fullname: 'Mastercard International',            color: '#eb001b', bin: null },
    { code: 'OTHER', name: 'Other / Khác',        fullname: 'Ngân hàng / tổ chức khác',            color: '#475569', bin: null },
];

// Generate logo URL from VietQR
VIETNAM_BANKS.forEach(b => {
    if (b.bin) {
        b.logo = `https://img.vietqr.io/image/${b.bin}-compact2.png`;
    } else if (b.code === 'MOMO') {
        b.logo = 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7a/MoMo-Logo.svg/80px-MoMo-Logo.svg.png';
    } else if (b.code === 'ZALO') {
        b.logo = 'https://stc-oa.zdn.vn/uploads/2022/04/29/image_1651207801_zalopay.png';
    } else if (b.code === 'VISA') {
        b.logo = 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/100px-Visa_Inc._logo.svg.png';
    } else if (b.code === 'MC') {
        b.logo = 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/80px-Mastercard-logo.svg.png';
    } else {
        b.logo = null;
    }
});
