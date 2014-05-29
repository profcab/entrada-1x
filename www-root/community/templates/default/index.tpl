<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset={$site_default_charset}" />

	<title>{$page_title}</title>

	<meta name="description" content="{$page_description}" />
	<meta name="keywords" content="{$page_keywords}" />

	<meta name="robots" content="index, follow" />

	<link href="{$sys_website_url}/css/jquery/jquery-ui.css" rel="stylesheet" type="text/css" />
	<script type="text/javascript" src="{$sys_website_url}/javascript/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="{$sys_website_url}/javascript/jquery/jquery-ui.min.js"></script>
	<script type="text/javascript">jQuery.noConflict();</script>

	<link href="{$template_relative}/css/stylesheet.css" rel="stylesheet" type="text/css" media="all" />
	<link href="{$template_relative}/css/bootstrap.min.css" rel="stylesheet" type="text/css" media="all" />

	<link href="{$template_relative}/css/print.css" rel="stylesheet" type="text/css" media="print" />

	<link href='http://fonts.googleapis.com/css?family=Roboto:400,400italic,700,700italic,300,300italic' rel='stylesheet' type='text/css'>

	{$page_head}

	<style type="text/css">
	#site-header {literal}{{/literal}
		background: transparent url('{$template_relative}/images/header-{$site_theme}.gif') no-repeat bottom;
	{literal}}{/literal}
	</style>
</head>
<body>
{$sys_system_navigator}
<div class="container">
	<div class="row">
		<div class="span2-5">
			{include file="navigation_primary.tpl" site_primary_navigation=$site_primary_navigation}
		</div>
		<div class="span9-5 content-container">
			<div class="row">
				<div class="span9-5">
					<div class="header table">
						<div class="table-cell">
							<div class="header-icon"></div>
						</div>
						<div class="table-cell table-cell-full-width">
							<div class="table">
								<div class="table-cell middle community-title">{$site_community_title}</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="span6-5 content-area">
					{$site_breadcrumb_trail}
					{$child_nav}
					<div class="content">
						{$page_content}
					</div>
					{if $is_sequential_nav}
						<div style="text-align:right;">
							{if $next_page_url != "#" && $previous_page_url != "#"}
								<p><a href="{$previous_page_url}"><< Previous</a> | <a href="{$next_page_url}">Next >></a></p>
							{elseif $next_page_url != "#" && $previous_page_url == "#"}
								<p> <a href="{$next_page_url}"> Next >></a></p>
							{elseif $next_page_url == "#" && $previous_page_url != "#"}
								<p> <a href="{$previous_page_url}"><< Previous</a> </p>
							{else $next_page_url == "#" && $previous_page_url == "#"}
								<p> </p>
							{/if}
						</div>
					{/if}
				</div>
				<div class="span3">
					{$page_sidebar}
				</div>
			</div>
			<div class="footer span9">
				<div class="content-copyright">
					{php}echo COPYRIGHT_STRING;{/php}
				</div>
			</div>
		</div>
	</div>
</div>
{php}if(((!defined("DEVELOPMENT_MODE")) || (!(bool) DEVELOPMENT_MODE)) && (defined("GOOGLE_ANALYTICS_CODE")) && (GOOGLE_ANALYTICS_CODE != "")) :{/php}
<script type="text/javascript">
	var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
	document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
	var pageTracker = _gat._getTracker("{php} echo GOOGLE_ANALYTICS_CODE;{/php}");
	pageTracker._initData();
	pageTracker._trackPageview();
</script>
{php}endif;{/php}
</body>
</html>