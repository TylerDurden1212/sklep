<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>❤️ Polubione produkty - GórkaSklep.pl</title>
<link rel="icon" href="./images/logo_strona.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --primary: #ff8c42;
    --secondary: #ff6b35;
    --dark: #2c3e50;
    --light: #fff5f0;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    min-height: 100vh;
}

.header {
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 15px 20px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 25px;
    align-items: center;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
}

.logo-icon { font-size: 48px; }
.logo-text { display: flex; flex-direction: column; }

.logo-main {
    font-size: 28px;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.logo-subtitle {
    font-size: 11px;
    color: #999;
    font-weight: 600;
}

.school-link {
    font-size: 18px;
    color: var(--primary);
    text-decoration: none;
}

.search-section { display: flex; gap: 10px; }
.search-bar { flex: 1; position: relative; }
.search-bar input {
    width: 100%;
    padding: 14px 50px 14px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 30px;
    font-size: 15px;
}

.search-btn {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: bold;
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 12px;
}

.menu-item {
    text-decoration: none;
    color: var(--dark);
    padding: 10px 18px;
    border-radius: 25px;
    background: var(--light);
    font-weight: 600;
    font-size: 14px;
    transition: 0.3s;
}

.menu-item:hover {
    background: var(--primary);
    color: white;
}

.btn-add {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: bold;
}

.container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

.page-header {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    margin-bottom: 30px;
    text-align: center;
}

.page-header h1 {
    font-size: 32px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
}

.page-header p {
    color: #666;
    font-size: 15px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
}

.product-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transition: 0.3s;
    cursor: pointer;
    position: relative;
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
}

.product-image-wrapper {
    position: relative;
    height: 280px;
    overflow: hidden;
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.like-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 3px 15px rgba(0,0,0,0.2);
    transition: 0.3s;
    z-index: 10;
}

.like-btn:hover {
    transform: scale(1.1);
}

.sold-badge {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: #10b981;
    color: white;
    padding: 10px;
    text-align: center;
    font-weight: bold;
    font-size: 16px;
}

.product-content {
    padding: 20px;
}

.product-title {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 10px;
    font-weight: 700;
}

.product-price {
    font-size: 26px;
    font-weight: bold;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 15px;
}

.seller-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 15px;
    border-top: 2px solid var(--light);
}

.seller-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
}

.seller-name {
    font-size: 13px;
    color: #666;
    font-weight: 600;
}

.empty-state {
    grid-column: 1/-1;
    background: white;
    border-radius: 20px;
    padding: 80px 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.empty-state-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.empty-state h2 {
    font-size: 28px;
    color: #333;
    margin-bottom: 15px;
}

.empty-state p {
    color: #666;
    font-size: 16px;
}

@media (max-width: 768px) {
    .header-content {
        grid-template-columns: 1fr;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo-section" onclick="window.location='index.php'">
            <div class="logo-icon"><img src="./images/logo.png" height="50px" width="50px"></div>
            <div class="logo-text">
                <div class="logo-main">GórkaSklep.pl</div>
                <div class="logo-subtitle">Szkolny Sklep Internetowy</div>
                <a href="https://lo2rabka.nowotarski.edu.pl" target="_blank" class="school-link" onclick="event.stopPropagation()">
                    Przejdź na naszą stronę szkoły! 🏫
                </a>
            </div>
        </div>
        
        <form class="search-section" method="get" action="index.php">
            <div class="search-bar">
                <input type="text" name="search" placeholder="Czego szukasz? 🔍">
                <button type="submit" class="search-btn">Szukaj</button>
            </div>
        </form>

        <div class="user-menu">
            <a href="index.php" class="menu-item">🏠 Strona główna</a>
            <a href="wiadomosci.php" class="menu-item">💬 Wiadomości</a>
            <a href="powiadomienia.php" class="menu-item">🔔 Powiadomienia</a>
            <a href="profil.php" class="menu-item">👤 Profil</a>
            <a href="polubione.php" class="menu-item">❤️ Polubione</a>
            <a href="dodaj_produkt.php" class="btn-add">+ Dodaj</a>
            <a href="logout.php" class="menu-item">Wyloguj</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="page-header">
        <h1>❤️ Polubione produkty</h1>
        <p>Produkty, które Ci się podobają</p>
    </div>

    <div class="products-grid" id="productsGrid">
        <!-- Products loaded by JavaScript -->
    </div>
</div>

<script>
async function loadLikedProducts() {
    try {
        const resp = await fetch('get_liked_products.php');
        const data = await resp.json();
        
        const container = document.getElementById('productsGrid');
        
        if (data.products.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">💔</div>
                    <h2>Brak polubionych produktów</h2>
                    <p>Zacznij przeglądać produkty i dodawaj je do ulubionych!</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = data.products.map(p => {
            const firstImage = p.zdjecie ? (typeof p.zdjecie === 'string' ? JSON.parse(p.zdjecie)[0] : p.zdjecie[0]) : null;
            
            return `
                <div class="product-card" onclick="window.location='produkt.php?id=${p.id}'">
                    <div class="product-image-wrapper">
                        <img src="${firstImage || 'https://via.placeholder.com/300x280'}" class="product-image" alt="${p.nazwa}">
                        <button class="like-btn" onclick="event.stopPropagation(); toggleLike(${p.id})">❤️</button>
                        ${p.is_sold == 1 ? '<div class="sold-badge">✅ Sprzedane!</div>' : ''}
                    </div>
                    <div class="product-content">
                        <div class="product-title">${p.nazwa}</div>
                        <div class="product-price">${parseFloat(p.cena).toFixed(2)} zł</div>
                        <div class="seller-info">
                            <div class="seller-name">👤 ${p.sprzedawca}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
    } catch(e) {
        console.error('Error loading products:', e);
    }
}

async function toggleLike(productId) {
    try {
        const formData = new FormData();
        formData.append('product_id', productId);
        
        const resp = await fetch('like_product.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await resp.json();
        
        if (data.success) {
            loadLikedProducts(); // Reload list
        }
    } catch(e) {
        console.error('Error toggling like:', e);
    }
}

loadLikedProducts();
</script>

</body>
</html>