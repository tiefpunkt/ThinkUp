<?php
/**
 *
 * ThinkUp/webapp/plugins/insightsgenerator/tests/TestOfResponseTimeInsight.php
 *
 * Copyright (c) 2013 Nilaksh Das, Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkup.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * Test of ResponseTimeInsight
 *
 * Test for the ResponseTimeInsight class.
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2013 Nilaksh Das, Gina Trapani
 * @author Nilaksh Das <nilakshdas [at] gmail [dot] com>
 */

require_once dirname(__FILE__) . '/../../../../tests/init.tests.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/autorun.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/web_tester.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsgenerator/model/class.InsightPluginParent.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsgenerator/insights/responsetime.php';

class TestOfResponseTimeInsight extends ThinkUpUnitTestCase {

    public function setUp(){
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function testGetSyntacticTimeDifference() {
        $delta_1 = 60 * 60 * 3; // 3 hours
        $delta_2 = 60 * 6; // 6 minutes
        $delta_3 = 60 * 60 * 24 * 4; // 4 days
        $delta_4 = 60 * 60 * 24; // 1 day

        $result_1 = ResponseTimeInsight::getSyntacticTimeDifference($delta_1);
        $result_2 = ResponseTimeInsight::getSyntacticTimeDifference($delta_2);
        $result_3 = ResponseTimeInsight::getSyntacticTimeDifference($delta_3);
        $result_4 = ResponseTimeInsight::getSyntacticTimeDifference($delta_4);

        $this->assertEqual($result_1, '3 hours');
        $this->assertEqual($result_2, '6 minutes');
        $this->assertEqual($result_3, '4 days');
        $this->assertEqual($result_4, '1 day');
    }

    public function testResponseTimeInsightForTwitterNoPriorBaseline() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'twitter';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 5,
            'retweet_count_cache' => 15,
            'favlike_count_cache' => 3
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 0,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 15
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 2,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 1
        ));

        // Calculate time for each new retweet
        $time_per_response = ResponseTimeInsight::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 25));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertIsA($result, "Insight");
        $this->assertPattern('/\@testeriffic\'s tweets averaged one new retweet/', $result->text);
        $this->assertPattern('/every '.$time_per_response.' over the last week./', $result->text);

        // Assert that baselines got inserted
        $insight_baseline_dao = new InsightBaselineMySQLDAO();
        $result_1 = $insight_baseline_dao->getInsightBaseline('response_count_replies', 10);
        $result_2 = $insight_baseline_dao->getInsightBaseline('response_count_retweets', 10);
        $result_3 = $insight_baseline_dao->getInsightBaseline('response_count_favs', 10);
        $this->assertNotNull($result_1);
        $this->assertNotNull($result_2);
        $this->assertNotNull($result_3);
        $this->assertIsA($result_1, "InsightBaseline");
        $this->assertIsA($result_2, "InsightBaseline");
        $this->assertIsA($result_3, "InsightBaseline");
        $this->assertEqual($result_1->value, 7);
        $this->assertEqual($result_2->value, 25);
        $this->assertEqual($result_3->value, 19);
    }

    public function testResponseTimeInsightForFacebookPriorGreaterBaseline() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'facebook';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 5,
            'retweet_count_cache' => 1,
            'favlike_count_cache' => 3
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 0,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 15
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 2,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 1
        ));

        // Add a baseline from prior week
        $last_week = date('Y-m-d', strtotime('-7 day'));
        $builder = FixtureBuilder::build('insight_baselines', array('date'=>$last_week, 'slug'=>'response_count_favs',
        'instance_id'=>10, 'value'=>27));

        // Calculate time for each new favorite
        $time_per_response = ResponseTimeInsight::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 19));
        $last_week_time_per_response = ResponseTimeInsight::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 27));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertIsA($result, "Insight");
        $this->assertPattern('/testeriffic\'s status updates averaged one new like/', $result->text);
        $this->assertPattern('/every '.$time_per_response.' over the last week,/', $result->text);
        $this->assertPattern('/slower than the previous week\'s average/', $result->text);
        $this->assertPattern('/of one like every '.$last_week_time_per_response.'./', $result->text);
    }

    public function testResponseTimeInsightForFoursquarePriorSmallerBaseline() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'foursquare';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 13,
            'retweet_count_cache' => 1,
            'favlike_count_cache' => 3
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 2,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 7
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 2,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 1
        ));

        // Add a baseline from prior week
        $last_week = date('Y-m-d', strtotime('-7 day'));
        $builder = FixtureBuilder::build('insight_baselines', array('date'=>$last_week, 'slug'=>'response_count_replies',
        'instance_id'=>10, 'value'=>12));

        // Calculate time for each new favorite
        $time_per_response = ResponseTimeInsight::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 17));
        $last_week_time_per_response = ResponseTimeInsight::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 12));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertIsA($result, "Insight");
        $this->assertPattern('/testeriffic\'s checkins averaged one new comment/', $result->text);
        $this->assertPattern('/every '.$time_per_response.' over the last week,/', $result->text);
        $this->assertPattern('/faster than the previous week\'s average/', $result->text);
        $this->assertPattern('/of one comment every '.$last_week_time_per_response.'./', $result->text);
    }
}