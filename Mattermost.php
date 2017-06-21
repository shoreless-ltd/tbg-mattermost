<?php

    namespace thebuggenie\modules\mattermost;

    use thebuggenie\core\entities\Milestone;
    use thebuggenie\core\entities\Project;
    use thebuggenie\core\entities\Issue;
    use thebuggenie\core\entities\Comment;
    use thebuggenie\core\framework;
    use thebuggenie\core\framework\I18n;
    use GuzzleHttp\Client as GuzzleClient;
    use ThibaudDauce\Mattermost\Mattermost as MattermostClient;
    use ThibaudDauce\Mattermost\Message as MattermostMessage;
    use ThibaudDauce\Mattermost\Attachment as MattermostAttachment;
    use League\HTMLToMarkdown\HtmlConverter;

    /**
     * Mattermost module for integrating The Bug Genie with Mattermost
     *
     * @author SHORELESS Limited
     * @version 1.0
     * @license http://opensource.org/licenses/MPL-2.0 Mozilla Public License 2.0 (MPL 2.0)
     * @package mattermost
     * @subpackage core
     */

    /**
     * Mattermost module for integrating with Mattermost
     *
     * @package mattermost
     * @subpackage core
     *
     * @Table(name="\thebuggenie\core\entities\tables\Modules")
     */
    class Mattermost extends \thebuggenie\core\entities\Module
    {

        const VERSION = '1.0';
        const SETTING_WEBHOOK_URL = 'webhook_url';
        const SETTING_PROJECT_INTEGRATION_ENABLED = 'project_integration_enabled_';
        const SETTING_PROJECT_CHANNEL_NAME = 'project_post_to_channel_';
        const SETTING_PROJECT_CHANNEL_LANGUAGE = 'project_post_language_';
        const SETTING_PROJECT_POST_AS_NAME = 'project_post_to_channel_as_name_';
        const SETTING_PROJECT_POST_AS_LOGO = 'project_post_to_channel_as_logo_';
        const SETTING_PROJECT_POST_ON_NEW_ISSUES = 'project_post_to_channel_on_new_issues_';
        const SETTING_PROJECT_POST_ON_NEW_RELEASES = 'project_post_to_channel_on_new_releases_';
        const SETTING_PROJECT_POST_ON_NEW_COMMENT = 'project_post_to_channel_on_new_comment_';

        protected $_has_config_settings = true;
        protected $_name = 'mattermost';
        protected $_longname = 'Mattermost integration';
        protected $_description = 'Mattermost description here';
        protected $_module_config_title = 'Mattermost integration';
        protected $_module_config_description = 'Configure the Mattermost integration';
        protected $_mattermost_config = [];

        /**
         * Localization clients
         *
         * Associative array of language key and
         * \thebuggenie\core\framework\I18n instances.
         *
         * @var array
         */
        protected static $_i18n = [];

        /**
         * Return an instance of this module
         *
         * @return Mattermost
         */
        public static function getModule()
        {
            return framework\Context::getModule('mattermost');
        }

        protected function _initialize()
        {
            require THEBUGGENIE_MODULES_PATH . 'mattermost' . DS . 'vendor' . DS . 'autoload.php';
            framework\Context::loadLibrary('ui');

            // Default settings.
            $this->_mattermost_config = [
                'username' => '',
                'channel' => '',
                'url' => '',
                'icon' => image_url(framework\Settings::getHeaderIconUrl(), framework\Settings::isUsingCustomHeaderIcon(), 'core', false),
                'link_names' => true,
                'truncate_text' => 300,
                'attachment_include_user' => false,
                'language' => 'en_US',
            ];
        }

        /**
         * Return project specific Mattermost posting settings
         *
         * @param Project $project
         *   The project object or a valid project ID.
         * @return array
         *   The project specific settings.
         */
        protected function _getSettings($project)
        {
            $project_id = $project->getID();

            $settings = $this->_mattermost_config;

            $settings['url'] = $this->getWebhookUrl($project_id);
            $settings['channel'] = $this->getChannelName($project_id);
            $settings['username'] = $this->getPostAsName($project_id);
            $settings['language'] = $this->getPostLanguage($project_id);
            if ($this->getPostAsLogo($project_id) != 'thebuggenie') {
                $settings['icon'] = image_url($project->getLargeIconName(), $project->hasLargeIcon(), 'core', false);
            }

            return $settings;
        }

        /**
         * Returns a localization client for the given language
         *
         * @param string $language
         * @return I18n
         */
        protected function _getI18n($language = 'en_US') {
            if (isset(self::$_i18n[$language])) {
                return self::$_i18n[$language];
            }

            $i18n = framework\Context::getI18n();
            if ( ! $i18n instanceof I18n || $i18n->getCurrentLanguage() != $language) {
                $i18n = new I18n($language);
            }
            self::$_i18n[$language] = $i18n;

            return $i18n;
        }

        /**
         * Event listener for new issues
         *
         * Posts to a Mattermost channel, if posting to Mattermost is enabled
         * for the project.
         *
         * @param \thebuggenie\core\framework\Event $event
         */
        public function listen_issueCreate(framework\Event $event)
        {
            $issue = $event->getSubject();
            $project_id = $issue->getProjectID();

            // Whether posting to Mattermost has been enabled.
            if ( ! ($issue instanceof Issue && $this->isProjectIntegrationEnabled($project_id) && $this->doesPostOnNewIssues($project_id))) {
                return;
            }
            
            // Get project specific settings.
            $settings = $this->_getSettings($issue->getProject());

            // Check for webhook URL.
            if (empty($settings['url'])) {
                return;
            }

            framework\Context::loadLibrary('common');
            $i18n = $this->_getI18n($settings['language']);

            // Mattermost client.
            $client = new MattermostClient(new GuzzleClient());
            $converter = new HtmlConverter(['strip_tags' => true]);

            // Issue properties.
            $issueNo = $issue->getFormattedIssueNo(true, true);
            $issueLink = framework\Context::getRouting()->generate('viewissue', ['project_key' => $issue->getProject()->getKey(), 'issue_no' => $issue->getIssueNo()], false);
            $issueDescription = $converter->convert($issue->getParsedDescription(['issue' => $issue]));
            if (! empty($settings['truncate_text'])) {
                $issueDescription = \tbg_truncateText($issueDescription);
            }
            $fields = [];

            // Meta information.
            if ($issue->hasIssueType()) {
                $fields[] = [
                    'title' => $i18n->__('Issue type'),
                    'value' => $issue->getIssueType()->getName(),
                    'short' => true,
                ];
            }

            // Compose message.
            $attachment = (new MattermostAttachment())
                ->fallback($i18n->__('New issue created'))
                ->title('[' . $issueNo . '] ' . $issue->getTitle(), $issueLink)
                ->text($issueDescription)
                ->success();

            if ( ! empty($fields)) {
                $attachment->fields($fields);
            }
            if ( ! empty($settings['attachment_include_user'])) {
                $attachment->authorName($settings['username'])
                    ->authorIcon($settings['icon']);
            }

            $message = (new MattermostMessage())
                ->channel($settings['channel'])
                ->username($settings['username'])
                ->iconUrl($settings['icon'])
                ->text($i18n->__('%user created [%issue_no](%issue_link) in project [%project_name](%project_link)', [
                    '%user' => ( ! empty($settings['link_names']) ? '@' : '') . $issue->getPostedBy()->getUsername(),
                    '%issue_no' => $issueNo,
                    '%issue_link' => $issueLink,
                    '%project_name' => $issue->getProject()->getName(),
                    '%project_link' => framework\Context::getRouting()->generate('project_dashboard', array('project_key' => $issue->getProject()->getKey()), false),
                ]))
                ->attachments([$attachment]);

            // Post the message to Mattermost.
            $client->send($message, $settings['url']);
        }

        /**
         * Event listener for new comments
         *
         * Posts to a Mattermost channel, if posting to Mattermost is enabled
         * for the project.
         *
         * @param \thebuggenie\core\framework\Event $event
         */
        public function listen_commentCreate(framework\Event $event)
        {
            $comment = $event->getSubject();

            // Whether the comment was not posted on an issue or was
            // system generated.
            if ( ! $comment instanceof Comment || $comment->getTargetType() != Comment::TYPE_ISSUE || $comment->isSystemComment()) {
                return;
            }

            $issue = $event->getParameter('issue');
            $project_id = $issue->getProjectID();

            // Whether posting on new comments is disabled for the project.
            if ( ! ($this->isProjectIntegrationEnabled($project_id) && $this->doesPostOnNewComments($project_id))) {
                return;
            }

            // Get project specific settings.
            $settings = $this->_getSettings($issue->getProject());

            // Check for webhook URL.
            if (empty($settings['url'])) {
                return;
            }

            framework\Context::loadLibrary('common');
            $i18n = $this->_getI18n($settings['language']);
            $converter = new HtmlConverter(['strip_tags' => true]);

            // Mattermost client.
            $client = new MattermostClient(new GuzzleClient());

            // Issue properties.
            $issueNo = $issue->getFormattedIssueNo(true, true);
            $issueLink = framework\Context::getRouting()->generate('viewissue', ['project_key' => $issue->getProject()->getKey(), 'issue_no' => $issue->getIssueNo()], false);
            $commentContent = $converter->convert($comment->getParsedContent());
            if ( ! empty($settings['truncate_text'])) {
                $commentContent = \tbg_truncateText($commentContent, $settings['truncate_text']);
            }
            $commentLink = $issueLink . '#comment_' . $comment->getID();

            // Compose message.
            $attachment = (new MattermostAttachment())
                ->fallback($i18n->__('New comment'))
                ->title($i18n->__('Comment #%comment_no', ['%comment_no' => $comment->getCommentNumber()]), $commentLink)
                ->text($commentContent)
                ->color('#666');

            if ( ! empty($settings['attachment_include_user'])) {
                $attachment->authorName($settings['username'])
                    ->authorIcon($settings['icon']);
            }

            $message = (new MattermostMessage())
                ->channel($settings['channel'])
                ->username($settings['username'])
                ->iconUrl($settings['icon'])
                ->text($i18n->__('%user commented on [%issue_no](%issue_link) in project [%project_name](%project_link)', [
                    '%user' => ( ! empty($settings['link_names']) ? '@' : '') . $comment->getPostedBy()->getUsername(),
                    '%issue_no' => '[' . $issueNo . '] ' . $issue->getTitle(),
                    '%issue_link' => $issueLink,
                    '%project_name' => $issue->getProject()->getName(),
                    '%project_link' => framework\Context::getRouting()->generate('project_dashboard', array('project_key' => $issue->getProject()->getKey()), false),
                ]))
                ->attachments([$attachment]);

            // Post the message to Mattermost.
            $client->send($message, $settings['url']);
        }

        /**
         * Event listener for new builds
         *
         * Posts to a Mattermost channel, if posting to Mattermost is enabled
         * for the project.
         *
         * @param \thebuggenie\core\framework\Event $event
         */
        public function listen_buildSave(framework\Event $event)
        {
            $release = $event->getSubject();
            $project = $release->getProject();
            $project_id = $project->getID();
            
            if (! ($this->isProjectIntegrationEnabled($project_id) && $this->doesPostOnNewReleases($project_id))) {
                return;
            }

            // Get project specific settings.
            $settings = $this->_getSettings($project);

            // Check for webhook URL.
            if (empty($settings['url'])) {
                return;
            }

            framework\Context::loadLibrary('common');
            $i18n = $this->_getI18n($settings['language']);
 
            $fields = [
                [
                    'title' => $i18n->__('Version number'),
                    'value' => $release->getVersion(),
                    'short' => true,
                ]
            ];
            if ($release->isReleased()) {
                $fields[] = [
                    'title' => $i18n->__('Release date'),
                    'value' => tbg_formatTime($release->getReleaseDate(), 20),
                    'short' => true,
                ];
            }
            if ($release->getMilestone() instanceof Milestone) {
                $fields[] = [
                    'title' => $i18n->__('Milestone'),
                    'value' => $release->getMilestone()->getName(),
                    'short' => true,
                ];
            }

            // Mattermost client.
            $client = new MattermostClient(new GuzzleClient());

            // Release data.
            $releaseName = $release->getName();
            $releaseLink = framework\Context::getRouting()->generate('project_releases', ['project_key' => $project->getKey()], false);

            // Compose message.
            $attachment = (new MattermostAttachment())
                ->fallback($i18n->__('New release'))
                ->title($releaseName, $releaseLink)
                ->color('#77A')
                ->fields($fields);

            if ( ! empty($settings['attachment_include_user'])) {
                $attachment->authorName($settings['username'])
                    ->authorIcon($settings['icon']);
            }

            $message = (new MattermostMessage())
                ->channel($settings['channel'])
                ->username($settings['username'])
                ->iconUrl($settings['icon'])
                ->text($i18n->__('New release [%release_name](%release_link) for project [%project_name](%project_link)', [
                    '%release_name' => $releaseName,
                    '%release_link' => $releaseLink,
                    '%project_name' => $project->getName(),
                    '%project_link' => framework\Context::getRouting()->generate('project_dashboard', array('project_key' => $project->getKey()), false),
                ]))
                ->attachments([$attachment]);

            // Post the message to Mattermost.
            $client->send($message, $settings['url']);
        }

        /**
         * Add event listeners
         */
        protected function _addListeners()
        {
            framework\Event::listen('core', 'thebuggenie\core\entities\Issue::createNew', array($this, 'listen_issueCreate'));
            framework\Event::listen('core', 'thebuggenie\core\entities\Comment::createNew', array($this, 'listen_commentCreate'));
            framework\Event::listen('core', 'thebuggenie\core\entities\Comment::_postSave', array($this, 'listen_commentCreate'));
            framework\Event::listen('core', 'thebuggenie\core\entities\Build::_postSave', array($this, 'listen_buildSave'));
            framework\Event::listen('core', 'config_project_tabs_other', array($this, 'listen_projectconfig_tab'));
            framework\Event::listen('core', 'config_project_panes', array($this, 'listen_projectconfig_panel'));
        }

        public function listen_projectconfig_tab(framework\Event $event)
        {
            include_component('mattermost/projectconfig_tab', array('selected_tab' => $event->getParameter('selected_tab'), 'module' => $this));
        }

        public function listen_projectconfig_panel(framework\Event $event)
        {
            include_component('mattermost/projectconfig_panel', array('selected_tab' => $event->getParameter('selected_tab'), 'access_level' => $event->getParameter('access_level'), 'project' => $event->getParameter('project'), 'module' => $this));
        }

        protected function _install($scope)
        {
            
        }

        protected function _loadFixtures($scope)
        {
            
        }

        protected function _uninstall()
        {
            
        }

        public function getWebhookUrl($project_id = 0)
        {
            if ( ! empty($project_id) && ! empty($url = $this->getSetting(self::SETTING_WEBHOOK_URL . '_' . $project_id))) {
                return $url;
            }
            return $this->getSetting(self::SETTING_WEBHOOK_URL);
        }

        /**
         * Sets the webhook URL.
         *
         * @param string $value
         *   Webhook URL
         * @param int $project_id
         *   Optional project ID. If not empty, the URL will be saved for the given
         *   project.
         * @return void
         */
        public function setWebhookUrl($value, $project_id)
        {
            $identifier = self::SETTING_WEBHOOK_URL;
            if ( ! empty($project_id)) {
                $identifier .= '_' . $project_id;
            }
            return $this->saveSetting($identifier, $value);
        }

        public function isProjectIntegrationEnabled($project_id)
        {
            return (bool) $this->getSetting(self::SETTING_PROJECT_INTEGRATION_ENABLED . $project_id);
        }

        public function setProjectIntegrationEnabled($project_id, $value)
        {
            return $this->saveSetting(self::SETTING_PROJECT_INTEGRATION_ENABLED . $project_id, $value);
        }

        public function getChannelName($project_id)
        {
            return $this->getSetting(self::SETTING_PROJECT_CHANNEL_NAME . $project_id);
        }

        public function setChannelName($project_id, $channel_name)
        {
            return $this->saveSetting(self::SETTING_PROJECT_CHANNEL_NAME . $project_id, $channel_name);
        }

        public function getPostAsName($project_id)
        {
            $setting = $this->getSetting(self::SETTING_PROJECT_POST_AS_NAME . $project_id);
            return $setting ?: 'TBG Autobot';
        }

        public function setPostAsName($project_id, $name)
        {
            return $this->saveSetting(self::SETTING_PROJECT_POST_AS_NAME . $project_id, $name);
        }

        public function getPostLanguage($project_id)
        {
            $setting = $this->getSetting(self::SETTING_PROJECT_CHANNEL_LANGUAGE . $project_id);
            return $setting ?: 'en_US';
        }

        public function setPostLanguage($project_id, $language)
        {
            return $this->saveSetting(self::SETTING_PROJECT_CHANNEL_LANGUAGE . $project_id, $language);
        }

        public function getPostAsLogo($project_id)
        {
            $setting = $this->getSetting(self::SETTING_PROJECT_POST_AS_LOGO . $project_id);
            return $setting ?: 'thebuggenie';
        }

        public function setPostAsLogo($project_id, $key)
        {
            return $this->saveSetting(self::SETTING_PROJECT_POST_AS_LOGO . $project_id, $key);
        }

        public function doesPostOnNewIssues($project_id, $value = null)
        {
            if ($value !== null) {
                return $this->saveSetting(self::SETTING_PROJECT_POST_ON_NEW_ISSUES . $project_id, (bool) $value);
            }
            else {
                $setting = $this->getSetting(self::SETTING_PROJECT_POST_ON_NEW_ISSUES . $project_id);
                return (isset($setting)) ? $setting : true;
            }
        }

        public function doesPostOnNewComments($project_id, $value = null)
        {
            if ($value !== null) {
                return $this->saveSetting(self::SETTING_PROJECT_POST_ON_NEW_COMMENT . $project_id, (bool) $value);
            }
            else {
                $setting = $this->getSetting(self::SETTING_PROJECT_POST_ON_NEW_COMMENT . $project_id);
                return (isset($setting)) ? $setting : true;
            }
        }

        public function doesPostOnNewReleases($project_id, $value = null)
        {
            if ($value !== null) {
                return $this->saveSetting(self::SETTING_PROJECT_POST_ON_NEW_RELEASES . $project_id, (bool) $value);
            }
            else {
                $setting = $this->getSetting(self::SETTING_PROJECT_POST_ON_NEW_RELEASES . $project_id);
                return (isset($setting)) ? $setting : true;
            }
        }

    }
    