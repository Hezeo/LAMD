// Sidebar Toggle Logic (Reuse from other pages)
const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');
allSideMenu.forEach(item => {
    const li = item.parentElement;
    item.addEventListener('click', () => {
        allSideMenu.forEach(i => i.parentElement.classList.remove('active'));
        li.classList.add('active');
    });
});
const menuBar = document.querySelector('#content nav .bx.bx-menu');
const sidebar = document.getElementById('sidebar');
if (menuBar && sidebar) menuBar.addEventListener('click', () => sidebar.classList.toggle('hide'));

// Profile Menu Toggle
const profile = document.querySelector('.profile');
const profileMenu = document.querySelector('.profile-menu');
if (profile && profileMenu) {
    profile.addEventListener('click', (e) => { e.stopPropagation(); profileMenu.classList.toggle('show'); });
}
window.addEventListener('click', (e) => {
    if (profileMenu && !e.target.closest('.profile')) profileMenu.classList.remove('show');
});