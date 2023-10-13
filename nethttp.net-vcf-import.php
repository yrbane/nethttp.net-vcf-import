<?php

/**
 * Plugin Name: nethttp.net-vcf-import
 * Plugin URI: https://github.com/yrbane/nethttp.net-vcf-import
 * Description: WordPress plugin that allows you to import users from uploaded VCF (vCard) files. It provides a user-friendly interface within the WordPress dashboard to upload these VCF files, extract contact data, and create corresponding users on your site. This plugin simplifies the process of adding new users by importing their information from VCF files, which can be especially useful for websites that require advanced user management or for data migration operations.
 * Version: 1.0.1
 * Author: Barney <yrbane@nethttp.net>
 * Author URI: https://github.com/yrbane
 * Requires PHP: 7.4
 * Text Domain: default
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path:       /languages
 */

// Include the vCard class
require_once(__DIR__ . '/vCard-parser/vCard.php');

if (!class_exists('BasePlugin')) {
    include_once(realpath(plugin_dir_path(__FILE__) . '../nethttp.net-base-plugin/nethttp.net-base-plugin.php'));
}

if (!class_exists('BasePlugin')) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>🙃 ' . __('Yous should install and activate nethttp.net-base-plugin. You can find it on ') . '<a href="https://github.com/yrbane/nethttp.net-base-plugin">github</a>!</p></div>';
    });
    return;
}



/**
 * Class VCFUploadCreateUsersAdmin
 *
 * This class defines the admin functionality for the VCF Upload Create Users plugin.
 *
 * @package VCFUploadCreateUsersAdmin
 */
class VCFUploadCreateUsersAdmin extends BasePlugin
{
    /**
     * The name of the option that stores the path to the user images directory.
     */
    const OPTION_PATH_TO_USER_IMAGES = 'path_to_user_images';

    protected string $plugin_name = 'nethttp.net-vcf-import';
    protected string $plugin_nice_name = "VCF Import";
    protected string $plugin_author = 'Barney';
    protected string $plugin_short_description = 'The "nethttp.net-vcf-import" plugin is an extension for WordPress that allows you to import users from uploaded VCF (vCard) files. It provides a user-friendly interface within the WordPress dashboard to upload these VCF files, extract contact data, and create corresponding users on your site. This plugin simplifies the process of adding new users by importing their information from VCF files, which can be especially useful for websites that require advanced user management or for data migration operations.';
    protected string $version = '1.0.1';
    protected string $github_url = 'https://github.com/yrbane/nethttp.net-vcf-import';

    /**
     * add_hooks: Add hooks for the plugin.   
     * 
     */
    protected function add_hooks(): void
    {
        parent::add_hooks();
        add_action('admin_init', [$this, 'admin_init']);
    }

    /**
     * admin_init: Add admin interface for the plugin.
     */
    public function admin_init(): void
    {
        register_setting('vcf_import_options', self::OPTION_PATH_TO_USER_IMAGES, function ($pathToUserImages) {
            //Validate the path exists otherwise return the previous value
            if (!is_dir($pathToUserImages)) {
                if (!empty($pathToUserImages)) {
                    //Create the directory relative to wp-content directory
                    if (!is_dir(WP_CONTENT_DIR . '/' . $pathToUserImages)) {
                        if (!mkdir(WP_CONTENT_DIR . '/' . $pathToUserImages, 0755, true)) {
                            $this->showError(__('The path to user images is not valid'));
                            return get_option(self::OPTION_PATH_TO_USER_IMAGES);
                        }
                    }
                } else {
                    $this->showError(__('The path is empty'));
                    return get_option(self::OPTION_PATH_TO_USER_IMAGES);
                }
            }
            return $pathToUserImages;
        });

        // Define the settings section and field
        add_settings_section('user_images', __('User Profile Images'), function () {
            echo '<p>' . __('Select where to store user profile images relative to wp-content folder') . ':</p>';
        }, 'vcf_import_options');

        add_settings_field(self::OPTION_PATH_TO_USER_IMAGES, __('Path to User Images'), function () {
            $path = get_option(self::OPTION_PATH_TO_USER_IMAGES);
            echo '<input type="text" name="' . self::OPTION_PATH_TO_USER_IMAGES . '" value="' . esc_attr($path) . '">';
        }, 'vcf_import_options', 'user_images');
    }
    /**
     * Displays the content of the admin page for the plugin.
     */
    public function admin_page_content(): void
    {
        // Display the settings page content
        echo '<div class="wrap"><h1>' . __('VCF Upload Create Users', 'default') . '</h1>';

        echo '<form enctype="multipart/form-data" method="post" action=""><h2>' . __('Fichier VCF', 'default') . '</h2>';

        wp_nonce_field('vcf_upload_nonce', 'vcf_upload_nonce');
        echo '<input type="file" name="vcf_file" accept=".vcf">';
        submit_button(__('Upload VCF to create users', 'default'), 'primary', 'upload_vcf');
        echo '</form><form method="post" action="options.php">';
        settings_fields('vcf_import_options');
        do_settings_sections('vcf_import_options');
        submit_button();
        echo '</form></div>';

        $this->process_vcf_upload();
        $this->process_user_creation();
    }


    /**
     * Display a summary table of imported contacts.
     *
     * @param array $contacts An array of imported contacts.
     * @param string $cat The category of contacts.
     */
    public function display_recap_table(array $contacts, string $cat = 'default'): void
    {
        if (empty($cat)) {
            $cat = __('Empty category');
        }
        $cathash = md5($cat);
        echo '<h2>' . $cat . '</h2>';

        echo '<table class="widefat table">
                <thead>
                    <tr>
                    <th><input type="checkbox" id="checkall-' . $cathash . '" name="checkall"></th>
                    <th>' . __('Name', 'default') . '</th>
                    <th>' . __('Email', 'default') . '</th>
                    <th>' . __('Address', 'default') . '</th>
                    <th>' . __('Phone', 'default') . '</th>
                    <th>' . __('Category', 'default') . '</th>
                    <th>' . __('Note', 'default') . '</th>
                    <th>' . __('Photo', 'default') . '</th>
                    <th>' . __('Role', 'default') . '<br/>
                    ' . $this->get_role_select('selected_role', 'subscriber', $cathash, 'role_select-' . $cathash) . '</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($contacts as $k => $contact) {
            echo '<tr class="data-row-' . $cathash . '"><td><input type="checkbox" value="1" name="selected[' . $k . ']" class="select-checkbox-' . $cathash . '"/></td><td>';
            if (isset($contact->n[0])) {
                echo $this->input('contacts[' . $k . '][lastname]', __('Last Name', 'default'), $contact->n[0]['LastName'], 'text') . ' ' . $this->input('contacts[' . $k . '][firstname]', __('First Name', 'default'), $contact->n[0]['FirstName'], 'text');
            }
            echo '</td><td>';
            foreach ($contact->email as $email) {
                echo $this->input('contacts[' . $k . '][email]', __('Email', 'default'), $email['Value'], 'text') . '<br>';
            }
            echo '</td><td>';
            foreach ($contact->adr as $adr) {
                foreach ($adr as $key_adr => $aelement) {
                    if (!is_array($aelement)) {
                        echo $this->input('contacts[' . $k . '][adr][' . $key_adr . ']', $key_adr, $aelement, 'text') . '<br>';
                    }
                }
            }
            echo '</td><td>';
            foreach ($contact->tel as $phone) {
                echo $this->input('contacts[' . $k . '][phone]', __('Phone', 'default'), $phone['Value'], 'text') . '<br>';
            }
            echo '</td><td>';
            if (isset($contact->categories[0])) {
                foreach ($contact->categories[0] as $ncat => $cat) {
                    echo $this->input('contacts[' . $k . '][cat][' . $ncat . ']', __('Category', 'default'), $cat, 'text') . '<br>';
                }
            }
            echo '</td><td>';
            $allnote = '';
            foreach ($contact->note as $nnote => $note) {
                $isnote = true;
                $allnote .= $note . "\n";
            }
            echo $this->input('contacts[' . $k . '][description]', __('Description', 'default'), $allnote, 'text');

            echo '</td><td>';
            foreach ($contact->photo as $photo) {
                echo '<img src="data:image/png;base64,' . $photo . '" /><br>' . $this->input('contacts[' . $k . '][photo]', __('Photo', 'default'), $photo, 'hidden');
            }
            echo '</td><td>';

            echo  $this->get_role_select('contacts[' . $k . '][role]', 'subscriber', $cathash); // Role column
            /*
            echo '</td><td>';
            foreach ($contact as $key => $value) {
                echo '<h3>' . $key . '</h3> <pre>' . print_r($value, true) . '</pre><br>';
            }
            */
            echo '</td></tr>';
        }
        echo '</tbody>
            </table>
            ';

?>
        <script>
            jQuery(document).ready(function($) {
                $('#checkall-<?php echo $cathash ?>').on('click', function() {
                    $('.select-checkbox-<?php echo $cathash ?>').prop('checked', $(this).prop('checked'));
                    updateRowFields<?php echo $cathash ?>();
                });

                $('.select-checkbox-<?php echo $cathash ?>').on('change', function() {
                    updateRowFields<?php echo $cathash ?>();
                });

                function updateRowFields<?php echo $cathash ?>() {
                    $('.data-row-<?php echo $cathash ?>').each(function() {
                        var isChecked = $(this).find('.select-checkbox-<?php echo $cathash ?>').prop('checked');
                        $(this).find('input[type="text"],input[type="hidden"]').prop('disabled', !isChecked);
                    });
                }

                $('#role_select-<?php echo $cathash ?>').on('change', function() {
                    var selectedRole = $(this).val();
                    console.log(selectedRole);
                    $('.role_select-<?php echo $cathash ?>').val(selectedRole);
                });

            });
        </script>
<?php
    }

    /**
     * Get a dropdown select input for selecting user roles.
     *
     * @param string $name The name attribute for the select input.
     * @param string $selected_role The role that should be pre-selected.
     *
     * @return string The HTML for the role select input.
     */
    protected function get_role_select(string $name, string $selected_role, string $cathash = 'default', $id = false): string
    {
        $roles = wp_roles()->get_names(); // Get all available user roles.

        $select = '<select name="' . esc_attr($name) . '" class="role_select-' . $cathash . '" ' . ($id !== false ? 'id="' . $id . '"' : '') . '>';

        // Iterate through each role and add it as an option in the select input.
        foreach ($roles as $role_value => $role_name) {
            $selected = selected($selected_role, $role_value, false); // Check if the role should be pre-selected.
            $select .= '<option value="' . esc_attr($role_value) . '" ' . $selected . '>' . esc_html($role_name) . '</option>';
        }

        $select .= '</select>';

        return $select;
    }

    /**
     * Generate an input field HTML element.
     *
     * @param string $name The name attribute for the input.
     * @param string $placeholder The placeholder attribute for the input.
     * @param string $value The value attribute for the input.
     * @param string $type The type attribute for the input (default: 'text').
     * @param int $index The index of the input (default: 0).
     *
     * @return string The input field HTML element.
     */
    function input(string $name, string $placeholder, string $value, string $type = 'text', int $index = 0): string
    {
        return '<input type="' . $type . '" name="' . $name . '" value="' . $value . '" placeholder="' . $placeholder . '" disabled style="width:100px;font-size:x-small;padding:2px;min-height:10px;" />';
    }


    /**
     * Process the uploaded VCF file and extract contact data.
     * @return bool True on success, false on failure.
     */
    function process_vcf_upload(): bool
    {
        if (
            isset($_POST['upload_vcf']) &&
            check_admin_referer('vcf_upload_nonce', 'vcf_upload_nonce') &&
            current_user_can('manage_options') &&
            $_FILES['vcf_file']['error'] === UPLOAD_ERR_OK
        ) {
            $vcf_data = file_get_contents($_FILES['vcf_file']['tmp_name']);
            $contacts = new vCard(false, $vcf_data);

            // Sort by categories
            $contacts_by_cat = [];
            foreach ($contacts as $k => $contact) {
                if (isset($contact->categories[0])) {
                    foreach ($contact->categories[0] as $ncat => $cat) {
                        if (!isset($contacts_by_cat[$cat])) {
                            $contacts_by_cat[$cat] = [];
                        }
                        $contacts_by_cat[$cat][$k] = $contact;
                    }
                }
            }
            unset($contacts);

            echo '<div class="wrap">
            <form method="post" action="">' . wp_nonce_field('vcf_create_user_nonce', 'vcf_create_user_nonce');
            foreach ($contacts_by_cat as $cat => $contacts) {
                // Display the recap table
                $this->display_recap_table($contacts, $cat);
            }
            echo '
            <input type="submit" value="' . __('Save selected rows as users', 'default') . '" class="btn button" name="vcf_create_user" />
            </form>
            </div>';

            return true;
        }
        return false;
    }

    /**
     * Generate a user login and nickname based on the first name of the contact.
     *
     * @param string $first_name The first name of the contact.
     * @return array An array containing the generated login and nickname.
     */
    public function generateUserLoginAndNickname($first_name)
    {
        // Use the first name to create a base login.
        $base_login = strtolower($first_name);

        // Generate a unique login by appending numbers if necessary.
        $login = $base_login;
        $suffix = 2;
        while (username_exists($login)) {
            $login = $base_login . $suffix;
            $suffix++;
        }

        // Set the nickname to the first name.
        $nickname = ucfirst(strtolower($first_name));

        return array(
            'user_login' => $login,
            'nickname' => $nickname,
        );
    }

    /**
     * Process the form submission to create or update users from imported contacts.
     */
    public function process_user_creation(): void
    {
        if (
            isset($_POST['vcf_create_user']) &&
            check_admin_referer('vcf_create_user_nonce', 'vcf_create_user_nonce') &&
            current_user_can('manage_options')
        ) {
            // Get contact data from the form
            $contacts = $_POST['contacts'];

            // Loop through the contacts
            foreach ($contacts as $index => $contact) {
                //var_dump($contact);
                //continue;
                if (!empty($contact['email'])) {

                    // if WP_DEBUG is enabled change the email to admin email to avoid sending email to the contact (change the email user@host.ext to user+firstname_lastname@host.ext)
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $contact['email'] = str_replace('@', '+' . $contact['firstname'] . '_' . $contact['lastname'] . '@', get_option('admin_email'));
                    }

                    // Check if the email already exists
                    $existing_user = get_user_by('email', $contact['email']);

                    $user_data = array(
                        'first_name' => $contact['firstname'],
                        'last_name' => $contact['lastname'],
                        //'user_url' => $contact['url'],
                        //'nickname' => $contact['nickname'],
                        'description' => $contact['note'] ?? '',
                        'display_name' => ucfirst(strtolower($contact['firstname'])) . ' ' . ucfirst(strtolower($contact['lastname'])),
                        'user_email' => $contact['email'],
                        //'user_login' => $contact['user_login'],
                        //'user_pass' => $contact['user_pass'], // Make sure to properly secure the password
                        //'user_nicename' => $contact['user_nicename'],
                        'user_registered' => date('Y-m-d H:i:s'),
                        'role' => $contact['role'], // User role
                        'user_status' => 0, // User status (0 = active, 1 = pending activation, -1 = suspended)
                        'show_admin_bar_front' => 'true', // Show admin bar on front end
                        'locale' => '', // User locale (e.g., 'fr_FR')
                        'rich_editing' => 'true', // Enable visual editor
                        'comment_shortcuts' => 'false', // Comment shortcuts
                        'admin_color' => 'fresh', // Admin color scheme (e.g., 'fresh' or 'classic')
                        'use_ssl' => '0', // Use SSL (0 = no, 1 = yes)
                        'show_admin_color_scheme_picker' => 'true', // Show admin color scheme picker
                        'wp_capabilities' => array('subscriber' => true), // User capabilities (default to subscriber)
                        'wp_user_level' => 0, // User level (0 = subscriber, 10 = administrator)
                        // Add other custom user fields here
                    );

                    if ($existing_user) {
                        // If the user exists, update their information
                        $user_id = $existing_user->ID;
                        $user_data['ID'] = $user_id; // Make sure to set the user ID

                        $updated_user = wp_update_user($user_data);

                        if (!is_wp_error($updated_user)) {
                            // User updated successfully
                            $this->showNotice(sprintf(__('User %s updated successfully'), '<i>' . $user_data['user_email'] . '</i>'));
                        } else {
                            // An error occurred while updating the user
                            $this->showError(sprintf(__('An error occurred while updating the user %s'), '<i>' . $user_data['user_email'] . '</i>'));
                        }
                    } else {
                        // Otherwise, create a new user
                        $user_login_and_nickname = $this->generateUserLoginAndNickname($contact['firstname']);
                        $user_data['user_login'] = $user_login_and_nickname['user_login'];
                        $user_data['nickname'] = $user_login_and_nickname['nickname'];
                        $user_data['user_pass'] = wp_generate_password();
                        $user_data['user_email'] = $contact['email'];
                        $user_data['user_nicename'] = sanitize_title($contact['firstname'] . ' ' . $contact['lastname']);
                        $user_data['user_registered'] = date('Y-m-d H:i:s');
                        $user_data['role'] = $contact['role'];

                        $user_id = wp_insert_user($user_data);

                        if (!is_wp_error($user_id)) {
                            $this->showNotice(sprintf(__('User %s created successfully'), '<i>' . $user_data['user_email'] . '</i>'));
                        } else {
                            // An error occurred while creating the user
                            $this->showError(sprintf(__('An error occurred while creating the user %s %s'), '<i>' . $user_data['user_email'] . '</i>', '<pre>' . print_r($user_id, true) . '</pre>'));
                        }
                    }

                    $user_data = array_merge($user_data, $contact);

                    if (isset($user_id) && !is_wp_error($user_id)) {
                        //set user meta
                        if (isset($contact['description'])) {
                            update_user_meta($user_id, 'description', $contact['description']);
                        }

                        if (isset($contact['adr'])) {
                            update_user_meta($user_id, 'adresse', json_encode($contact['adr']));
                        }

                        $this->update_user_profile_image($user_id, $user_data);
                    }
                }
            }

            // Redirect the user to the homepage or another page of your choice
            wp_redirect(admin_url('admin.php?page=vcf-upload-create-users'));
            exit;
        }
    }

    /**
     * Update the user profile image.
     *
     * @param int $user_id The ID of the user.
     * @param array $contact The contact data.
     */
    protected function update_user_profile_image(int $user_id, array $contact): void
    {
        // Get db
        global $wpdb;

        // User profile images upload directory
        $pathRelativeToWpContent = get_option(self::OPTION_PATH_TO_USER_IMAGES);
        $pathToUserImages = WP_CONTENT_DIR . '/' . $pathRelativeToWpContent;

        if (!is_dir($pathToUserImages)) {
            $this->showError(sprintf(__('The path %s to user images do not exists'), $pathToUserImages));
            return;
        }

        if (!is_writable($pathToUserImages)) {
            $this->showError(sprintf(__('The path %s to user images is not writable'), $pathToUserImages));
            return;
        }

        if (isset($contact['photo'])) {
            $photoSum = md5($contact['photo']);
            $image_name = $contact['firstname'] . '-' . $contact['lastname'] . '.png';
            $image_path = $pathToUserImages . '/' . $image_name;

            if (is_file($image_path) && get_user_meta($user_id, $wpdb->prefix . 'user_avatar_sum', true) == $photoSum) {
                $this->showWarning(sprintf(__('The photo already exists')));
                return;
            }

            $image = base64_decode($contact['photo']);
            
            file_put_contents($image_path, $image);

            $image_url = WP_CONTENT_URL . $pathRelativeToWpContent . '/' . $image_name;
            //Save image as media
            $attachment = array(
                'guid'           => $image_url,
                'post_mime_type' => 'image/png',
                'post_title'     => $image_name,
                'post_content'   => '',
                'post_status'    => 'inherit'
            );

            $attachment_id = attachment_url_to_postid($image_url);
            if ($attachment_id) {
                wp_delete_attachment($attachment_id);   
                $this->showNotice(sprintf(__('Delete old attachment for %s'), '<i>' . $contact['user_email'] . '</i>'));
            }

            // Insert the attachment into the media library
            $attachment_id = wp_insert_attachment($attachment, $image_path);    

            // generate metadata for attachment
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $image_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);

            // Optional: set the attachment as the featured image of an article
            // $post_id is the ID of the post you want to associate the image with
            //set_post_thumbnail($post_id, $attachment_id);

            // Work with One User Avatar plugin
            update_user_meta($user_id, $wpdb->prefix . 'user_avatar', $attachment_id);

            update_user_meta($user_id, $wpdb->prefix . 'user_avatar_sum', $photoSum);
        } else {
            $this->showError(sprintf(__('No photo for %s'), '<i>' . $contact['user_email'] . '</i>'));
        }
    }

    protected function loadPluginFunctionality()
    {
        $this->plugin_short_description = __('The "nethttp.net-vcf-import" plugin is an extension for WordPress that allows you to import users from uploaded VCF (vCard) files. It provides a user-friendly interface within the WordPress dashboard to upload these VCF files, extract contact data, and create corresponding users on your site. This plugin simplifies the process of adding new users by importing their information from VCF files, which can be especially useful for websites that require advanced user management or for data migration operations.') . ' ' . __('Work with') . ' <a href="/wp-admin/plugin-install.php?tab=plugin-information&plugin=one-user-avatar">One user avatar</a>';
    }
}

// Instantiate the VCFUploadCreateUsersAdmin class
new VCFUploadCreateUsersAdmin(__FILE__);
