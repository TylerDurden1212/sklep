<!-- Dodaj to do profil.php i produkt.php aby wyświetlać oceny -->

<style>
.rating-section {
    background: var(--card-bg);
    padding: 25px;
    border-radius: 20px;
    border: 2px solid var(--border-color);
    margin: 20px 0;
}

.rating-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.rating-stars {
    display: flex;
    gap: 5px;
    font-size: 28px;
}

.star {
    color: #ddd;
    transition: 0.2s;
    cursor: pointer;
}

.star.filled {
    color: #fbbf24;
    animation: starPop 0.3s ease-out;
}

@keyframes starPop {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.star:hover {
    transform: scale(1.1);
}

.rating-summary {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rating-value {
    font-size: 48px;
    font-weight: bold;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.rating-details {
    display: flex;
    flex-direction: column;
}

.rating-count {
    font-size: 14px;
    color: #999;
}

.rating-form {
    display: none;
    margin-top: 20px;
    padding: 20px;
    background: var(--hover-bg);
    border-radius: 15px;
}

.rating-form.show {
    display: block;
    animation: slideDown 0.3s;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-color);
}

.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
    background: var(--card-bg);
    color: var(--text-color);
}

.rating-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    color: white;
}

.rating-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 140, 66, 0.4);
}

.reviews-list {
    margin-top: 30px;
}

.review-item {
    background: var(--hover-bg);
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 15px;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.review-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.review-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff8c42 0%, #ff6b35 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.review-stars {
    font-size: 16px;
}

.review-comment {
    color: var(--text-color);
    line-height: 1.6;
}

.review-date {
    font-size: 12px;
    color: #999;
    margin-top: 8px;
}
</style>

<?php
// Funkcja do wyświetlania ocen - dodaj do config.php
function getRatingHTML($seller_id, $can_rate = false, $product_id = null) {
    $conn = getDBConnection();
    
    // Pobierz statystyki
    $stmt = $conn->prepare("SELECT rating_avg, rating_count FROM logi WHERE id = ?");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $avg = $stats['rating_avg'] ? round($stats['rating_avg'], 1) : 0;
    $count = $stats['rating_count'] ?? 0;
    
    // Pobierz opinie
    $stmt = $conn->prepare("
        SELECT r.*, l.username, l.profile_picture 
        FROM ratings r
        LEFT JOIN logi l ON r.buyer_id = l.id
        WHERE r.seller_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $conn->close();
    
    ob_start();
    ?>
    
    <div class="rating-section">
        <div class="rating-header">
            <div class="rating-summary">
                <?php if ($count > 0): ?>
                    <div class="rating-value"><?= $avg ?></div>
                    <div class="rating-details">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= round($avg) ? 'filled' : '' ?>">⭐</span>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-count"><?= $count ?> <?= $count == 1 ? 'ocena' : 'ocen' ?></div>
                    </div>
                <?php else: ?>
                    <div class="rating-value">-</div>
                    <div class="rating-details">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star">⭐</span>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-count">Brak ocen</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($can_rate && $product_id): ?>
                <button class="rating-btn" onclick="toggleRatingForm()">
                    ⭐ Wystaw ocenę
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($can_rate && $product_id): ?>
        <div class="rating-form" id="ratingForm">
            <div class="form-group">
                <label>Twoja ocena:</label>
                <div class="rating-stars" id="userRating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star" data-rating="<?= $i ?>" onclick="selectRating(<?= $i ?>)">⭐</span>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Komentarz (opcjonalnie):</label>
                <textarea id="ratingComment" placeholder="Podziel się swoją opinią..."></textarea>
            </div>
            
            <button class="rating-btn" onclick="submitRating(<?= $seller_id ?>, <?= $product_id ?>)">
                Wyślij ocenę
            </button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($reviews)): ?>
        <div class="reviews-list">
            <h3 style="margin-bottom: 15px;">📝 Opinie użytkowników</h3>
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="review-user">
                            <div class="review-avatar">
                                <?= strtoupper(substr($review['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <strong><?= htmlspecialchars($review['username']) ?></strong>
                        </div>
                        <div class="review-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>">⭐</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if (!empty($review['comment'])): ?>
                        <div class="review-comment"><?= htmlspecialchars($review['comment']) ?></div>
                    <?php endif; ?>
                    <div class="review-date">
                        <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    let selectedRating = 0;
    
    function toggleRatingForm() {
        const form = document.getElementById('ratingForm');
        form.classList.toggle('show');
    }
    
    function selectRating(rating) {
        selectedRating = rating;
        const stars = document.querySelectorAll('#userRating .star');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('filled');
            } else {
                star.classList.remove('filled');
            }
        });
    }
    
    async function submitRating(sellerId, productId) {
        if (selectedRating === 0) {
            showToast('warning', 'Uwaga', 'Wybierz ocenę gwiazdkową');
            return;
        }
        
        const comment = document.getElementById('ratingComment').value;
        
        try {
            const formData = new FormData();
            formData.append('seller_id', sellerId);
            formData.append('produkt_id', productId);
            formData.append('rating', selectedRating);
            formData.append('comment', comment);
            
            const resp = await fetch('rate_seller.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await resp.json();
            
            if (data.success) {
                showToast('success', 'Sukces!', 'Twoja ocena została zapisana');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Błąd', data.error || 'Nie udało się zapisać oceny');
            }
        } catch(e) {
            console.error('Error:', e);
            showToast('error', 'Błąd', 'Wystąpił błąd podczas zapisywania oceny');
        }
    }
    </script>
    
    <?php
    return ob_get_clean();
}
?>

<!-- Przykład użycia w produkt.php: -->
<?php
// Po sekcji sprzedawcy dodaj:
// if ($product['is_sold'] == 1 && !empty($_SESSION['user_id']) && $product['buyer_id'] == $_SESSION['user_id']) {
//     echo getRatingHTML($product['seller_id'], true, $product['id']);
// } else {
//     echo getRatingHTML($product['seller_id'], false);
// }
?>