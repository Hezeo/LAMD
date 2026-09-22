<a href="#" class="brand">
    <img src="../images/web_icon.png" alt="LAND ASSET MANAGEMENT">
    <span class="text">LAND ASSET MANAGEMENT DEPARTMENT</span>
    <small class="text-short">L.A.M.D.</small>
</a>
<ul class="side-menu top">
    <?php 
    // Loop through all permissions stored in the session
    if (!empty($_SESSION['user_permissions'])):
        foreach ($_SESSION['user_permissions'] as $perm_name => $perm_data):
            
            // Check if user has view or edit access
            $access_level = is_array($perm_data) ? ($perm_data['can'] ?? '') : $perm_data;
            
            // Only show if permission is 'view' or 'edit'
            if ($access_level === 'view' || $access_level === 'edit'):
                
                // Extract data
                $link = $perm_data['link'] ?? '#';
                $icon = $perm_data['icon'] ?? 'bx-file'; // Fallback to generic icon
                
                // Check if this is the current page to set 'active' class
                $current_page = basename($_SERVER['PHP_SELF']);
                $is_active = ($current_page == $link) ? 'active' : '';
    ?>
                <li class="<?php echo $is_active; ?>">
                    <a href="<?php echo htmlspecialchars($link); ?>">
                        <!-- Icon comes directly from DB now -->
                        <i class='bx <?php echo htmlspecialchars($icon); ?> bx-sm'></i>
                        <span class="text" style="display: grid;"><?php echo htmlspecialchars($perm_name); ?></span>
                    </a>
                </li>
    <?php 
            endif; 
        endforeach; 
    endif; 
    ?>
</ul>

<style>
    /* ==========================================
       2. SIDEBAR
       ========================================== */
    #sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 220px;
        height: 100%;
        background: var(--light);
        z-index: 2000;
        font-family: var(--lato);
        transition: .3s ease;
        overflow-x: hidden;
        scrollbar-width: none;
    }

    #sidebar::-webkit-scrollbar { display: none; }
    #sidebar.hide { width: 60px; }

    #sidebar .brand {
        font-size: 12px;
        font-weight: 700;
        height: auto;
        min-height: 56px;
        display: flex;
        flex-direction: column;
        align-items: center !important;
        justify-content: center !important;
        color: var(--dark);
        position: sticky;
        top: 0;
        left: 0;
        background: var(--light);
        z-index: 500;
        padding-bottom: 20px;
        margin-top: 15px;
        margin-left: 5px;
        box-sizing: content-box;
        text-align: center;
        overflow: visible;
    }

    #sidebar .brand img {
        height: 80px;
        width: auto;
        object-fit: contain;
        margin: 0 auto 5px auto; /* Centers logo horizontally */
        display: block;
        transition: height .3s ease, margin .3s ease;
    }

    /* --- SMOOTH TEXT TRANSITION --- */
    #sidebar .brand .text,
    #sidebar .brand .text-short {
        transition: opacity 0.2s ease, transform 0.2s ease;
        transform-origin: center top;
    }

    /* Hide abbreviation by default when sidebar is open */
    #sidebar .brand .text-short {
        position: absolute;
        opacity: 0;
        transform: scale(0.8);
        pointer-events: none;
    }

    /* --- SIDEBAR HIDDEN STATES --- */

    /* Logo shrink when sidebar is hidden */
    #sidebar.hide .brand img {
        height: 40px;
        margin: 0 auto 5px auto; /* Keeps logo centered */
    }

    /* INSTANTLY kill long text without the glitch behind the logo */
    #sidebar.hide .brand .text {
        opacity: 0;
        width: 0 !important; 
        overflow: hidden;
        white-space: nowrap;
        margin: 0;
        padding: 0;
        transform: scale(1); 
    }

    /* Safely show short text perfectly centered under logo */
    #sidebar.hide .brand .text-short {
        opacity: 1;
        transform: scale(1);
        position: relative !important; 
        width: 100%; /* Forces the text container to span the whole 60px sidebar width */
        text-align: center; /* Centers the LAMD text within that width */
        top: -18px;
        font-size: 12px;
    }

    /* --- SIDEBAR MENU --- */
    #sidebar .brand .bx { min-width: 60px; display: flex; justify-content: center; }
    #sidebar .side-menu { width: 100%; margin-top: 48px; }

    #sidebar .side-menu li {
        height: 48px;
        background: transparent;
        margin-left: 6px;
        border-radius: 48px 0 0 48px;
        padding: 4px;
    }

    #sidebar .side-menu li.active { background: var(--grey); position: relative; }

    #sidebar .side-menu li.active::before {
        content: ''; position: absolute; width: 40px; height: 40px;
        border-radius: 50%; top: -40px; right: 0;
        box-shadow: 20px 20px 0 var(--grey); z-index: -1;
    }

    #sidebar .side-menu li.active::after {
        content: ''; position: absolute; width: 40px; height: 40px;
        border-radius: 50%; bottom: -40px; right: 0;
        box-shadow: 20px -20px 0 var(--grey); z-index: -1;
    }

    #sidebar .side-menu li a {
        width: 100%; height: 100%; background: var(--light);
        display: flex; align-items: center; border-radius: 48px;
        font-size: 16px; color: var(--dark);
        white-space: nowrap; overflow-x: hidden;
    }

    #sidebar .side-menu.top li.active a { color: var(--dark); font-weight: 600; background: var(--light) !important; }
    #sidebar.hide .side-menu li a { width: calc(48px - (4px * 2)); transition: width .3s ease; }
    #sidebar .side-menu li a.logout { color: var(--red); }
    #sidebar .side-menu.top li a:hover { color: var(--dark); background: #f4f7fa; }
    #sidebar .side-menu li a .bx { min-width: calc(60px - ((4px + 6px) * 2)); display: flex; justify-content: center; }
    #sidebar .side-menu.bottom li:nth-last-of-type(-n+2) { position: absolute; bottom: 0; left: 0; right: 0; text-align: center; }
    #sidebar .side-menu.bottom li:nth-last-of-type(2) { bottom: 40px; }
    html.sidebar-preload-hide #sidebar { margin-left: -260px; transition: none !important; }


    /* --- CONTENT --- */
    #content {
        position: relative;
        width: calc(100% - 220px);
        left: 220px;
        transition: .3s ease;
        display: flex;
        flex-direction: column;
        height: 100vh;
    }

    #sidebar.hide~#content { width: calc(100% - 60px); left: 60px; }
    #sidebar.hide ~ #content main .table-data .line-charts { flex-grow: 1; flex-basis: 150px; width: auto; }


    /* --- SIDEBAR DARK MODE --- */
    body.dark #sidebar { border-right-color: var(--card-border); }
    body.dark #sidebar .brand { background: var(--light); }
    body.dark #sidebar .side-menu li a { color: var(--dark-grey); }
    body.dark #sidebar .side-menu li a:hover { color: #A9C7AC; background: linear-gradient(135deg, #1b5e1f55 0%, #00000000 100%);}
    body.dark #sidebar .side-menu.top li.active a { color: #2ecc71; background: linear-gradient(135deg, #1b5e1f55 0%, #00000000 100%) !important; }

    

/* #sidebar .brand {
    font-size: 12px;
    font-weight: 700;
    height: 56px;
    display: flex;
    flex-direction: column;
    align-items: center !important;
    justify-content: center !important;
    color: var(--dark);
    position: sticky;
    top: 0;
    left: 0;
    background: var(--light);
    z-index: 500;
    padding-bottom: 20px;
    margin-top: 50px;
    margin-left: 5px;
    box-sizing: content-box;
    text-align: center;
}

#sidebar .brand img {
    height: 80px;
    width: auto;
    object-fit: contain;
    margin: 0 auto 5px auto;
    display: block;
} */

</style>

<script>
    // Your JS here
</script>