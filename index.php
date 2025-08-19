<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/database.php';

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    ob_end_flush();
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM Users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get tweets with user info, likes count, and comments count
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.username,
            u.profile_picture,
            COUNT(DISTINCT l.like_id) as likes_count,
            COUNT(DISTINCT c.comment_id) as comments_count,
            EXISTS(SELECT 1 FROM Likes WHERE user_id = ? AND tweet_id = t.tweet_id) as user_liked
        FROM Tweets t
        LEFT JOIN Users u ON t.user_id = u.user_id
        LEFT JOIN Likes l ON t.tweet_id = l.tweet_id
        LEFT JOIN Comments c ON t.tweet_id = c.tweet_id
        GROUP BY t.tweet_id
        ORDER BY t.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tweets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching tweets: " . $e->getMessage());
    $tweets = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Twitter Clone</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
    .tweet-image-container {
        position: relative;
        display: inline-block;
        width: 100%;
        margin-top: 10px;
    }

    .tweet-image {
        max-width: 100%;
        border-radius: 15px;
    }

    .copy-image-link {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.2s ease;
        z-index: 10;
    }

    .tweet-image-container:hover .copy-image-link {
        opacity: 1;
    }

    .copy-image-link:hover {
        background: rgba(0, 0, 0, 0.9);
    }

    .copy-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        z-index: 1000;
        animation: fadeInOut 2s ease;
    }

    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(20px); }
        15% { opacity: 1; transform: translateY(0); }
        85% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-20px); }
    }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <a href="index.php" class="nav-brand">Twitter Clone</a>
            </div>
            <div class="nav-right">
                <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                <a href="logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Welcome to Twitter Clone</h1>
        <p>This is a test page.</p>

        <!-- Tweet Form -->
        <div class="tweet-form-container">
            <form action="post_tweet.php" method="POST" enctype="multipart/form-data" class="tweet-form">
                <textarea name="content" placeholder="What's happening?" required></textarea>
                <div class="tweet-form-footer">
                    <div class="tweet-form-actions">
                        <label for="image-upload" class="image-upload-label">
                            <i class="fas fa-image"></i>
                            <input type="file" id="image-upload" name="image" accept="image/*" style="display: none;">
                        </label>
                    </div>
                    <button type="submit" class="tweet-submit-button">
                        <span class="button-text">Tweet</span>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Tweets Feed -->
        <div class="tweets-container">
            <?php if (empty($tweets)): ?>
                <div class="no-tweets">
                    <p>No tweets yet. Be the first to tweet!</p>
                </div>
            <?php else: ?>
                <?php foreach ($tweets as $tweet): ?>
                    <div class="tweet-card" data-tweet-id="<?php echo $tweet['tweet_id']; ?>">
                        <div class="tweet-header">
                            <img src="<?php echo $tweet['profile_picture'] ?? 'images/default-avatar.png'; ?>" 
                                 alt="Profile Picture" 
                                 class="tweet-avatar">
                            <div class="tweet-user-info">
                                <a href="profile.php?username=<?php echo urlencode($tweet['username']); ?>" 
                                   class="tweet-username">
                                    @<?php echo htmlspecialchars($tweet['username']); ?>
                                </a>
                                <span class="tweet-date">
                                    <?php echo date('M d', strtotime($tweet['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="tweet-content">
                            <?php echo nl2br(htmlspecialchars($tweet['content'])); ?>
                            <?php if (!empty($tweet['image_url'])): ?>
                                <div class="tweet-image-container">
                                    <img src="<?php echo htmlspecialchars($tweet['image_url']); ?>" 
                                         alt="Tweet Image" 
                                         class="tweet-image">
                                    <button class="copy-image-link" 
                                            onclick="copyImageLink('<?php echo htmlspecialchars($tweet['image_url']); ?>')"
                                            title="Copy image link">
                                        <i class="fas fa-link"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tweet-actions">
                            <button class="like-button <?php echo $tweet['user_liked'] ? 'liked' : ''; ?>"
                                    onclick="likeTweet(<?php echo $tweet['tweet_id']; ?>)">
                                <i class="fas fa-heart"></i>
                                <span class="likes-count"><?php echo $tweet['likes_count']; ?></span>
                            </button>
                            <button class="comment-button" 
                                    onclick="showComments(<?php echo $tweet['tweet_id']; ?>)">
                                <i class="fas fa-comment"></i>
                                <span class="comments-count"><?php echo $tweet['comments_count']; ?></span>
                            </button>
                        </div>
                        
                        <div class="comments-section" id="comments-<?php echo $tweet['tweet_id']; ?>">
                            <form class="comment-form" onsubmit="submitComment(event, <?php echo $tweet['tweet_id']; ?>)">
                                <div class="comment-input-wrapper">
                                    <input type="text" class="comment-input" placeholder="Write a comment..." required>
                                    <button type="button" class="emoji-button" onclick="toggleEmojiPicker(this)">
                                        <i class="far fa-smile"></i>
                                    </button>
                                </div>
                                <button type="submit" class="comment-submit">Comment</button>
                            </form>
                            <div class="comments-list">
                                <!-- Comments will be loaded here -->
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script>
    function copyImageLink(imageUrl) {
        // Create a temporary input element
        const tempInput = document.createElement('input');
        tempInput.value = imageUrl;
        document.body.appendChild(tempInput);
        
        // Select and copy the text
        tempInput.select();
        document.execCommand('copy');
        
        // Remove the temporary input
        document.body.removeChild(tempInput);
        
        // Show notification
        const notification = document.createElement('div');
        notification.className = 'copy-notification';
        notification.textContent = 'Link copied!';
        document.body.appendChild(notification);
        
        // Remove notification after 2 seconds
        setTimeout(() => {
            notification.remove();
        }, 2000);
    }
    </script>
</body>
</html>
