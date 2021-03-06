<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for cleaner and uncleaner
 *
 * Avoid adding new tests to this file as it is mostly used as legacy tests.
 *
 * @package    local_cleanurls
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_cleanurls\local\uncleaner\uncleaner;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__.'/cleanurls_testcase.php');

/**
 * Tests for cleaner and uncleaner
 *
 * @package    local_cleanurls
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_cleanurls_simple_test extends local_cleanurls_testcase {
    public function test_local_cleanurls_simple() {
        global $CFG;

        // Create some test users, courses and modules.
        $this->resetAfterTest(true);

        $thiscategory = $this->getDataGenerator()->create_category(['name' => 'sciences']);
        $thiscategory2 = $this->getDataGenerator()->create_category(['name' => 'compsci', 'parent' => $thiscategory->id]);

        $thiscourse = $this->getDataGenerator()->create_course([
                                                                   'fullname'  => 'full#course',
                                                                   'shortname' => 'short#course',
                                                                   'visible'   => 1,
                                                                   'category'  => $thiscategory->id,
                                                               ]);

        $thismancourse = $this->getDataGenerator()->create_course([
                                                                      'fullname'  => 'Some course',
                                                                      'shortname' => 'management',
                                                                      'visible'   => 1,
                                                                      'category'  => $thiscategory->id,
                                                                  ]);

        $thispublishcourse = $this->getDataGenerator()->create_course([
                                                                          'fullname'  => 'Full!course@name',
                                                                          'shortname' => 'publish',
                                                                          'visible'   => 1,
                                                                          'category'  => $thiscategory->id,
                                                                      ]);

        $thisforum = $this->getDataGenerator()->create_module('forum',
                                                              ['course' => $thiscourse->id, 'name' => 'A!test@FORUM#5']);

        $thisstaff = $this->getDataGenerator()->create_user(['email' => 'head1@example.com', 'username' => 'head1']);
        $this->setUser($thisstaff);

        $CFG->urlrewriteclass = local_cleanurls\url_rewriter::class;
        set_config('cleaningon', 1, 'local_cleanurls');
        set_config('enableurlrewrite', 1);
        purge_all_caches();

        // Test the cleaning and uncleaning rules.
        set_config('cleaningon', 0, 'local_cleanurls');
        set_config('cleanusernames', 0, 'local_cleanurls');
        purge_all_caches();

        $url = 'http://www.example.com/moodle/course/view.php?id=' . $thiscourse->id;
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Urls shouldn't be touched if cleaning setting is off");

        $url = 'http://www.example.com/moodle/local/cleanurls/tests/foo.php';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals('http://www.example.com/moodle/local/cleanurls/tests/bar',
                            $clean, "Test url should be cleaned even if cleaning is off");

        $CFG->urlrewriteclass = local_cleanurls\url_rewriter::class;
        set_config('cleaningon', 1, 'local_cleanurls');
        set_config('enableurlrewrite', 1);
        purge_all_caches();

        $url = 'http://www.example.com/moodle/course/view.php?id=' . $thispublishcourse->id;
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals(
            'http://www.example.com/moodle/course/view.php?id=' . $thispublishcourse->id,
            $clean,
            "Urls to course with name \"publish\" are not supposed to be cleaned because they clash with a directory."
        );

        $url = 'http://www.example.com/moodle/theme/whatever.php';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Nothing: Theme files should not be touched");

        $url = 'http://www.example.com/moodle/lib/whatever.php';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Nothing: Lib files should not be touched");

        $url = 'http://www.example.com/moodle/help.php?blah=foo';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Nothing: Help files should not be touched");

        $url = 'http://www.example.com/moodle/pluginfile.php/12345/foo/bar';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Nothing: Plugin files should not be touched");

        $url = 'http://moodle.test/moodle/draftfile.php/5/user/draft/949704188/daniel-roperto.jpg';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Nothing: File draftfile.php should not be touched");

        $url = 'http://www.example.com/moodle/foo/bar.php';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals('http://www.example.com/moodle/foo/bar.php', $clean, "Clean: Don't remove php extension");

        $unclean = uncleaner::unclean($clean)->raw_out();
        $this->assertDebuggingCalled('Could not unclean until the end of address: foo/bar.php');
        $this->assertEquals($url, $unclean, "Unclean: Put php extension back");

        $url = 'http://www.example.com/moodle/foo/bar.php?ding=pop';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals('http://www.example.com/moodle/foo/bar.php?ding=pop', $clean,
                            "Clean: Do not remove php extension with params");

        $unclean = uncleaner::unclean($clean)->raw_out();
        $this->assertDebuggingCalled('Could not unclean until the end of address: foo/bar.php');
        $this->assertEquals($url, $unclean, "Unclean: Put php extension back with params");

        $url = 'http://www.example.com/moodle/foo/bar.php#hash';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals('http://www.example.com/moodle/foo/bar.php#hash', $clean,
                            "Clean: Don't remove php extension with hash");

        $unclean = uncleaner::unclean($clean)->raw_out();
        $this->assertDebuggingCalled('Could not unclean until the end of address: foo/bar.php');
        $this->assertEquals($url, $unclean, "Unclean: Put php extension back with hash");

        $url = 'http://www.example.com/moodle/course/index.php?foo=bar#hash';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals('http://www.example.com/moodle/course/?foo=bar#hash', $clean, "Clean: Remove index");

        $url = 'http://www.example.com/moodle/admin/settings.php?section=local_cleanurls';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Clean: Don't clean any admin paths");

        $url = 'http://www.example.com/moodle/auth/foo/bar.php';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Clean: Don't clean any auth paths");

        $url = 'http://www.example.com/moodle/course/view.php?id=' . $thiscourse->id;
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals('http://www.example.com/moodle/course/short%23course', $clean, "Clean: course");

        $url = 'http://www.example.com/moodle/course/view.php?id=' . $thismancourse->id;
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals('http://www.example.com/moodle/course/view.php?id=' . $thismancourse->id, $clean,
                            "Clean: course is ignored because it's shortname clashes with dir or file");

        $url = 'http://www.example.com/moodle/course/index.php';
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals('http://www.example.com/moodle/course/', $clean, "Clean: index.php off url");

        // Nothing to unclean because these urls will get routed directly by apache not router.php.

        $url = 'http://www.example.com/moodle/user/profile.php?id=' . $thisstaff->id;
        $murl = new moodle_url($url);
        $clean = $murl->out();
        $this->assertEquals($url, $clean, "Not Cleaned: user profile url with username");

        $url = 'http://www.example.com/moodle/user/view.php?id=' . $thisstaff->id . '&course=' . $thiscourse->id;
        $murl = new moodle_url($url);
        $clean = $murl->out(false);
        $this->assertEquals('http://www.example.com/moodle/user/view.php?id=' . $thisstaff->id . '&course=' . $thiscourse->id,
                            $clean, "Not Cleaned: user profile url with username inside course");
    }
}
