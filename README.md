# AppBase.io Reporter

A reporter to send profiler data to [AppBase.io](https://appbase.io) (Hosted [ElasticSearch](https://www.elastic.co/products/elasticsearch))

# How to install

* Setup an app with appbase using ElasticSearch 7
* Drop `1_wpprofiler_appbase_reporter.php` into the `mu-plugins` folder. Be sure you have the latest [WP Profiler](https://github.com/WPProfiler/core) installed as well.
* Define the constants `WP_PROFILER_APPBASE_APP_NAME` and `WP_PROFILER_APPBASE_APP_API_KEY` in your wp-config.php. These will be the app id/name you made on appbase and the Admin (or other key with write access) API key in the form of `USERNAME:PASSWORD`
* Since reports are processed via cron in a deferred/batched manner, It is recommended you use WP-CLI to ensure fast processing vs wp-cron.php or the default cron, but it will work regardless.

Once done you csan start querying AppBase via any of [their supported methods](https://docs.appbase.io/docs/gettingstarted/QuickStart/) and have fun :)
