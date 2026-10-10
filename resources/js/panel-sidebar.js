export function panelSidebar() {
    return {
        open: true,
        mobileOpen: false,
        init() {
            this.open = window.localStorage.getItem('zentra-sidebar-open') !== '0';
        },
        toggle() {
            this.open = ! this.open;
            window.localStorage.setItem('zentra-sidebar-open', this.open ? '1' : '0');
        },
        openMobile() {
            this.mobileOpen = true;
        },
        closeMobile() {
            this.mobileOpen = false;
        },
        onNavClick(event) {
            if (event.target.closest('a')) {
                this.closeMobile();
            }
        },
    };
}
