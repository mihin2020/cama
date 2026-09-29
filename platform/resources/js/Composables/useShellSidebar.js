import { onMounted, onUnmounted } from 'vue';

export function useShellSidebar() {
    const openSidebar = () => document.body.classList.add('sidebar-open');
    const closeSidebar = () => document.body.classList.remove('sidebar-open');
    const toggleSidebar = () => document.body.classList.toggle('sidebar-open');

    onMounted(() => {
        document.body.classList.add('bg-surface-container-low', 'text-on-background', 'font-body-md');
    });

    onUnmounted(() => {
        document.body.classList.remove('sidebar-open', 'bg-surface-container-low', 'text-on-background', 'font-body-md');
    });

    return { openSidebar, closeSidebar, toggleSidebar };
}

export function roleCanSee(role, roles) {
    if (!roles) return true;
    return roles.split(',').includes(role);
}
