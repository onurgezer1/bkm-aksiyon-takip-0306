<?php
/**
 * BKM Aksiyon Takip - Frontend Login Template
 */
?>

<div class="bkm-login-container">
    <h2><?php _e('Giriş Yap', 'bkm-aksiyon-takip'); ?></h2>
    
    <?php
    // Display error message if exists
    if ($error = get_transient('bkm_login_error')) {
        echo '<div class="bkm-error-message">' . esc_html($error) . '</div>';
        delete_transient('bkm_login_error');
    }
    ?>
    
    <form method="post" action="<?php echo esc_url(wp_login_url()); ?>" class="bkm-login-form">
        <p>
            <label for="log"><?php _e('Kullanıcı Adı veya E-posta', 'bkm-aksiyon-takip'); ?></label>
            <input type="text" name="log" id="log" class="input" value="" required>
        </p>
        
        <p>
            <label for="pwd"><?php _e('Şifre', 'bkm-aksiyon-takip'); ?></label>
            <input type="password" name="pwd" id="pwd" class="input" required>
        </p>
        
        <p>
            <label><input type="checkbox" name="rememberme" value="forever"> <?php _e('Beni Hatırla', 'bkm-aksiyon-takip'); ?></label>
        </p>
        
        <p>
            <input type="submit" name="bkm_login_submit" class="button button-primary" value="<?php _e('Giriş Yap', 'bkm-aksiyon-takip'); ?>">
            <input type="hidden" name="bkm_nonce" value="<?php echo wp_create_nonce('bkm_login_nonce'); ?>">
        </p>
    </form>
    
    <p>
        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php _e('Şifremi Unuttum', 'bkm-aksiyon-takip'); ?></a>
    </p>
</div>

<style>
.bkm-login-container {
    max-width: 400px;
    margin: 50px auto;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.bkm-login-form p {
    margin-bottom: 15px;
}

.bkm-login-form label {
    display: block;
    margin-bottom: 5px;
}

.bkm-login-form .input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.bkm-error-message {
    background: #ffebee;
    color: #c62828;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 4px;
}

.bkm-login-form .button-primary {
    background: #0073aa;
    border-color: #006799;
    padding: 8px 20px;
    cursor: pointer;
}
</style>