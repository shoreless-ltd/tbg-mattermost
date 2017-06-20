<?php

    namespace thebuggenie\modules\mattermost;

    use thebuggenie\core\framework;

    /**
     * actions for the mattermost module
     */
    class Components extends framework\ActionComponent
    {

        /**
         * @return \thebuggenie\modules\mattermost\Mattermost
         * @throws \Exception
         */
        protected function _getModule()
        {
            return framework\Context::getModule('mattermost');
        }

        public function componentSettings()
        {
            $this->webhook_url = $this->_getModule()->getWebhookUrl();
        }

        public function componentProjectconfig_panel()
        {
            $this->integration_enabled = $this->module->isProjectIntegrationEnabled($this->project->getID());
        }

    }

