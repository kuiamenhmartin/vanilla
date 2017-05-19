<?php
/**
 * VanillaStats Plugin
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package VanillaStats
 */

$PluginInfo['VanillaStats'] = array(
    'Name' => 'Vanilla Statistics',
    'Description' => 'Adds helpful graphs and information about activity on your forum over time (new users, discussions, comments, and pageviews).',
    'Version' => '2.0.7',
    'MobileFriendly' => true,
    'RequiredApplications' => array('Vanilla' => '2.0.18'),
    'Icon' => 'vanilla_stats.png',
    'Author' => "Vanilla Staff",
    'AuthorEmail' => 'support@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.com'
);

/**
 * This plugin tracks pageviews on the forum and reports them to the central Vanilla
 * Analytics System.
 *
 * Changes:
 *  1.0     Official release
 *  2.0.3   Fix http/https issue
 */
class VanillaStatsPlugin extends Gdn_Plugin {

    /**  */
    const RESOLUTION_DAY = 'day';

    /**  */
    const RESOLUTION_MONTH = 'month';

    /** @var mixed  */
    public $AnalyticsServer;

    /** @var string  */
    public $VanillaID;

    /**
     * VanillaStatsPlugin constructor.
     */
    public function __construct() {
        $this->AnalyticsServer = c('Garden.Analytics.Remote', 'analytics.vanillaforums.com');
        $this->VanillaID = Gdn::installationID();
    }

    /**
     * Override the default dashboard page with the new stats one.
     */
    public function gdn_dispatcher_beforeDispatch_handler($Sender) {
        $Enabled = c('Garden.Analytics.Enabled', true);

        if ($Enabled) {
            Gdn::pluginManager()->registerNewMethod('VanillaStatsPlugin', 'StatsDashboard', 'SettingsController', 'home');
        }
    }

    /**
     *
     *
     * @param $JsonResponse
     * @param $RawResponse
     */
    public function securityTokenCallback($JsonResponse, $RawResponse) {
        $SecurityToken = val('SecurityToken', $JsonResponse, null);
        if (!is_null($SecurityToken)) {
            $this->securityToken($SecurityToken);
        }
    }

    /**
     * Get the security token.
     *
     * @param null|string $SetSecurityToken
     * @return string
     */
    protected function securityToken($SetSecurityToken = null) {
        static $SecurityToken = null;

        if (!is_null($SetSecurityToken)) {
            $SecurityToken = $SetSecurityToken;
        }

        if (is_null($SecurityToken)) {
            $Request = array('VanillaID' => $this->VanillaID);
            Gdn::statistics()->basicParameters($Request);
            Gdn::statistics()->analytics('graph/getsecuritytoken.json', $Request, array(
                'Success' => array($this, 'SecurityTokenCallback')
            ));
        }
        return $SecurityToken;
    }

    /**
     * Override the index of the dashboard's settings controller in the to render new statistics.
     *
     * @param SettingsController $sender Instance of the dashboard's settings controller.
     */
    public function settingsController_home_create($sender) {
        $statsUrl = $this->AnalyticsServer;
        if (!stringBeginsWith($statsUrl, 'http:') && !stringBeginsWith($statsUrl, 'https:')) {
            $statsUrl = Gdn::request()->scheme()."://{$statsUrl}";
        }

        Gdn_Theme::section('DashboardHome');
        $sender->setData('IsWidePage', true);

        // Tell the page where to find the Vanilla Analytics provider
        $sender->addDefinition('VanillaStatsUrl', $statsUrl);
        $sender->setData('VanillaStatsUrl', $statsUrl);

        // Load javascript & css, check permissions, and load side menu for this page.
        $sender->addJsFile('settings.js');
        $sender->title(t('Dashboard'));
        $sender->RequiredAdminPermissions = [
            'Garden.Settings.View',
            'Garden.Settings.Manage',
            'Garden.Community.Manage',
        ];
        $sender->fireEvent('DefineAdminPermissions');
        $sender->permission($sender->RequiredAdminPermissions, '', false);
        $sender->setHighlightRoute('dashboard/settings');

        if (!Gdn_Statistics::checkIsEnabled() && Gdn_Statistics::checkIsLocalhost()) {
            $sender->render('dashboardlocalhost', '', 'plugins/VanillaStats');
        } else {
            $sender->addCssFile('vendors/c3.min.css', 'plugins/VanillaStats');
            $sender->addJsFile('vanillastats.js', 'plugins/VanillaStats');
            $sender->addJsFile('d3.min.js');
            $sender->addJsFile('c3.min.js');

            $sender->addDefinition('VanillaID', Gdn::installationID());
            $sender->addDefinition('AuthToken', Gdn_Statistics::generateToken());

            $sender->addDefinition('ExpandText', t('more'));
            $sender->addDefinition('CollapseText', t('less'));

            $isVanillaAnalyticEnabled = Gdn::addonManager()->isEnabled('vanillaanalytics', Vanilla\Addon::TYPE_ADDON);
            $sender->addDefinition('DashboardSummaries', c('Garden.Analytics.DashboardSummaries', !$isVanillaAnalyticEnabled));

            // Render the custom dashboard view
            $sender->render('dashboard', '', 'plugins/VanillaStats');
        }
    }

    /**
     * A view containing most active discussions & users during a specific time
     * period. This gets ajaxed into the dashboard homepage as date ranges are defined.
     */
    public function settingsController_dashboardSummaries_create($Sender) {
        $DiscussionData = [];
        $UserData = [];
        $isVanillaAnalyticEnabled = Gdn::addonManager()->isEnabled('vanillaanalytics', Vanilla\Addon::TYPE_ADDON);

        if (c('Garden.Analytics.DashboardSummaries', !$isVanillaAnalyticEnabled)) {
            $range = Gdn::request()->getValue('range');
            $range['to'] = date(MYSQL_DATE_FORMAT, strtotime($range['to']));
            $range['from'] = date(MYSQL_DATE_FORMAT, strtotime($range['from']));

            $UserModel = new UserModel();

            // Load the most active discussions during this date range
            $DiscussionData = $UserModel->SQL
                ->select('d.DiscussionID, d.Name, d.CountBookmarks, d.CountViews, d.CountComments, d.CategoryID, d.DateInserted')
                ->from('Discussion d')
                ->where('d.DateLastComment >=', $range['from'])
                ->where('d.DateLastComment <=', $range['to'])
                ->orderBy('d.CountViews', 'desc')
                ->orderBy('d.CountComments', 'desc')
                ->orderBy('d.CountBookmarks', 'desc')
                ->limit(5, 0)
                ->get();

            $Structure = Gdn::structure()->table('Comment');

            // If row count > than 10M and range is greater than 3 months.
            $rowCountEstimate = $Structure->getRowCountEstimate('Comment');
            $toDateTime = new DateTime($range['to']);
            $dateDiff = date_diff($toDateTime, new DateTime($range['from']));
            if ($rowCountEstimate >= 10000000 && $dateDiff->format('%a') > 90) {
                $range['from'] = ($toDateTime->sub(new DateInterval('P3M')))->format(MYSQL_DATE_FORMAT);
                $Sender->setData('UserDataRangeClamped', true);
            } else {
                $Sender->setData('UserDataRangeClamped', false);
            }

            // Load the most active users during the date range.
            $UserModel->SQL
                ->select('InsertUserID as UserID')
                ->select('CommentID', 'count', 'CountComments')
                ->from('Comment')
                ->where('DateInserted >=', $range['from'])
                ->where('DateInserted <=', $range['to'])
                ->groupBy('InsertUserID')
                ->orderBy('CountComments', 'desc')
                ->limit(5, 0);

            // We need to help the MySQL optimiser in some weird cases.
            $Indexes = $Structure->indexSqlDb();
            if (isset($Indexes['IX_Comment_DateInserted'])) {
                $UserModel->SQL->from('Comment2');
            }

            // Make a copy before calling reset();
            $NamedParameters = array_merge([], $UserModel->SQL->namedParameters());
            $Query = $UserModel->SQL->getSelect();
            $UserModel->SQL->reset();

            // Force index usage. The MySQL optimizer can sometime, depending on the data structure,
            // use FK_Comment_InsertUserID instead of IX_Comment_DateInserted which is way slower on large DateInserted range.
            if (isset($Indexes['IX_Comment_DateInserted'])) {
                $Query = preg_replace('/(\nfrom .+Comment.+?)(,.+Comment2.+)(\nwhere)/', "$1\nforce index (IX_Comment_DateInserted)$3", $Query);
            }

            $Query = $UserModel->SQL->applyParameters($Query, $NamedParameters);
            $UserData = $UserModel->SQL->query($Query);
        }

        $Sender->setData('DiscussionData', $DiscussionData);
        $Sender->setData('UserData', $UserData);

        // Load javascript & css, check permissions, and load side menu for this page.
        $Sender->addJsFile('settings.js');
        $Sender->title(t('Dashboard Summaries'));

        $Sender->RequiredAdminPermissions = [
            'Garden.Settings.View',
            'Garden.Settings.Manage',
            'Garden.Community.Manage',
        ];

        $Sender->fireEvent('DefineAdminPermissions');
        $Sender->permission($Sender->RequiredAdminPermissions, '', false);
        $Sender->setHighlightRoute('dashboard/settings');

        // Render the custom dashboard view
        $Sender->render('dashboardsummaries', '', 'plugins/VanillaStats');
    }
}
