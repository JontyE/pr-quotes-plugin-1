<?php
// File: includes/admin-settings.php

// Debug log to confirm inclusion
error_log('admin-settings.php included');

function pr_quotes_render_settings_tab() {
    error_log('pr_quotes_render_settings_tab executed');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pr_quotes_settings_submit'])) {
        $delete_data_option = isset($_POST['delete_data_option']) && $_POST['delete_data_option'] === 'yes' ? 'yes' : 'no';
        update_option('pr_quotes_delete_data_on_uninstall', $delete_data_option);

        echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
    }

    $delete_data_option = get_option('pr_quotes_delete_data_on_uninstall', 'no');
    ?>
    <div class="wrap">
        <h2>Settings</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="delete_data_option">Delete all data on uninstall</label>
                    </th>
                    <td>
                        <label>
                            <input type="radio" name="delete_data_option" value="yes" <?php checked($delete_data_option, 'yes'); ?>>
                            Yes, delete all data (drop tables)
                        </label><br>
                        <label>
                            <input type="radio" name="delete_data_option" value="no" <?php checked($delete_data_option, 'no'); ?>>
                            No, keep all data
                        </label>
                        <p class="description">Choose whether to delete all plugin-related data (database tables) when the plugin is uninstalled.</p>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" name="pr_quotes_settings_submit" class="button button-primary">Save Settings</button>
            </p>
        </form>
    </div>
    <?php
    error_log('pr_quotes_render_settings_tab executed');

}
