<?php

	//-----------------------------------------------------------------------------
	//
	//  nbbc_parse.php
	//
	//  This file is part of NBBC, the New BBCode Parser.
	//
	//  NBBC implements a fully-validating, high-speed, extensible parser for the
	//  BBCode document language.  Its output is XHTML 1.0 Strict conformant no
	//  matter what its input is.  NBBC supports the full standard BBCode language,
	//  as well as comments, columns, enhanced quotes, spoilers, acronyms, wiki
	//  links, several list styles, justification, indentation, and smileys, among
	//  other advanced features.
	//
	//-----------------------------------------------------------------------------
	//
	//  Copyright (c) 2008-9, the Phantom Inker.  All rights reserved.
	//
	//  Redistribution and use in source and binary forms, with or without
	//  modification, are permitted provided that the following conditions
	//  are met:
	//
	//    * Redistributions of source code must retain the above copyright
	//       notice, this list of conditions and the following disclaimer.
	//
	//    * Redistributions in binary form must reproduce the above copyright
	//       notice, this list of conditions and the following disclaimer in
	//       the documentation and/or other materials provided with the
	//       distribution.
	//
	//  THIS SOFTWARE IS PROVIDED BY THE PHANTOM INKER "AS IS" AND ANY EXPRESS
	//  OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	//  WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	//  DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
	//  LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
	//  CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	//  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
	//  BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
	//  WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
	//  OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
	//  IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
	//
	//-----------------------------------------------------------------------------
	//
	//  This file implements the New BBCode parser.  Usage is simple:  Just create
	//  a BBCode object, and then call $bbcode->parse() with a string containing
	//  BBCode, and it returns HTML.
	//
	//  Internally, this constructs and validates a syntax tree for the BBCode,
	//  so the output HTML is *always* valid HTML --- except that the resulting
	//  output is *not* wrapped in a container <div> or <span> element automatically;
	//  if you need a wrapper, add one yourself.
	//
	//  This also replaces smileys with their respective images.
	//
	//  This class works by building the BBCode on a stack as an implict tree of
	//  operators and operands, somewhat like parsing a math expression, using
	//  the tags' class-containment rules like operator precedences.  The stack
	//  is used to determine what's legal and what's not, and to eventually
	//  "evaluate" the BBCode into HTML output.  This technique (a push-down
	//  automaton) is equivalent to building a real document tree from the input,
	//  but it's much faster and requires much less memory, in exchange for much
	//  more conceptually-convoluted code:  Don't modify Internal_GenerateOutput,
	//  Internal_RewindToClass, Internal_FinishTag, or any function with "Parse"
	//  in its name function unless you know *exactly* what you're doing.  In
	//  fact, the less you change here the better, because this class is very
	//  tightly-knit, and even small changes can have big ramifications.  If you
	//  do make a change, be sure to test it with the included conformance suite.
	//
	//  Note:  For performance reasons, we use $array[]= instead of array_push;
	//  and we use output buffering rather than string concatenation.  These seem
	//  to both yield slightly higher performance than their alternative solutions,
	//  even if they're a little stranger to read.
	//
	//-----------------------------------------------------------------------------

	class BBCode {

		//-----------------------------------------------------------------------------
		// Instance variables.  Do not change any of these directly!  Use the
		// access methods provided below.

		var $tag_rules;		// List of tag rules currently in use.
		var $defaults;		// The standard library (an instance of class BBCodeLibrary).

		var $current_class;	// The current class (auto-computed).
		var $root_class;	// The root container class.
		var $lost_start_tags; // For repair when tags are badly mis-nested.
		var $start_tags;	// An associative array of locations of start tags on the stack.
		var $allow_ampersand; // If true, we use str_replace() instead of htmlspecialchars().
		var $tag_marker;	// Set to '[', '<', '{', or '('.
		var $ignore_newlines; // If true, newlines will be treated as normal whitespace.
		var $plain_mode;	// Don't output tags:  Just output text/whitespace/newlines only.

		var $detect_urls;	// Should we audo-detect URLs and convert them to links?
		var $url_pattern;	// What to convert auto-detected URLs into.

		var $output_limit;	// The maximum number of text characters to output.
		var $text_length;	// The number of text characters output so far.
		var $was_limited;	// Set to true if the output was cut off.
		var $limit_tail;	// What to add if the output is cut off.
		var $limit_precision; // How accurate should we be if we're cutting off text?

		var $smiley_dir;	// The host filesystem path to smileys (should be an absolute path).
		var $smiley_url;	// The URL path to smileys (possibly a relative path).
		var $smileys;		// The current list of smileys.
		var $smiley_regex;	// This is a regex, precomputed from the list of smileys above.
		var $enable_smileys; // Whether or not to perform smiley-parsing.

		var $wiki_url;		// URL prefix used for [[wiki]] links.

		var $local_img_dir;	// The host filesystem path to local images (should be an absolute path).
		var $local_img_url;	// The URL path to local images (possibly a relative path).
		var $url_targetable; // If true, [url] tags can accept a target="..." parameter.
		var $url_target;	// If non-false, [url] tags will use this target and no other.

		var $rule_html;		// The default HTML to output for a [rule] tag.

		var $pre_trim;		// How to trim the whitespace at the start of the input.
		var $post_trim;		// How to trim the whitespace at the end of the input.

		var $debug;			// Enable debugging mode? (lots of output)

		//-----------------------------------------------------------------------------
		// Constructor.

		function bBCode() {
			$this->defaults = new BBCodeLibrary;
			$this->tag_rules = $this->defaults->default_tag_rules;
			$this->smileys = $this->defaults->default_smileys;
			$this->enable_smileys = true;
			$this->smiley_regex = false;
			$this->smiley_dir = $this->getDefaultSmileyDir();
			$this->smiley_url = $this->getDefaultSmileyURL();
			$this->wiki_url = $this->getDefaultWikiURL();
			$this->local_img_dir = $this->getDefaultLocalImgDir();
			$this->local_img_url = $this->getDefaultLocalImgURL();
			$this->rule_html = $this->getDefaultRuleHTML();
			$this->pre_trim = "";
			$this->post_trim = "";
			$this->root_class = 'block';
			$this->lost_start_tags = [];
			$this->start_tags = [];
			$this->tag_marker = '[';
			$this->allow_ampsersand = false;
			$this->current_class = $this->root_class;
			$this->debug = false;
			$this->ignore_newlines = false;
			$this->output_limit = 0;
			$this->plain_mode = false;
			$this->was_limited = false;
			$this->limit_tail = "...";
			$this->limit_precision = 0.15;
			$this->detect_urls = false;
			$this->url_pattern = '<a href="{$url/h}">{$text/h}</a>';
			$this->url_targetable = false;
			$this->url_target = false;
		}

		//-----------------------------------------------------------------------------
		// State control.

		function setPreTrim($trim = "a") { $this->pre_trim = $trim; }
		function getPreTrim()          { return $this->pre_trim; }
		function setPostTrim($trim = "a") { $this->post_trim = $trim; }
		function getPostTrim()         { return $this->post_trim; }

		function setRoot($class = 'block') { $this->root_class = $class; }
		function setRootInline()       { $this->root_class = 'inline'; }
		function setRootBlock()        { $this->root_class = 'block'; }
		function getRoot()             { return $this->root_class; }

		function setDebug($enable = true) { $this->debug = $enable; }
		function getDebug()            { return $this->debug; }

		function setAllowAmpersand($enable = true) { $this->allow_ampersand = $enable; }
		function getAllowAmpersand()   { return $this->allow_ampersand; }
		function setTagMarker($marker = '[') { $this->tag_marker = $marker; }
		function getTagMarker()        { return $this->tag_marker; }

		function setIgnoreNewlines($ignore = true) { $this->ignore_newlines = $ignore; }
		function getIgnoreNewlines()   { return $this->ignore_newlines; }

		function setLimit($limit = 0)  { $this->output_limit = $limit; }
		function getLimit()            { return $this->output_limit; }
		function setLimitTail($tail = "...") { $this->limit_tail = $tail; }
		function getLimitTail()        { return $this->limit_tail; }
		function setLimitPrecision($prec = 0.15) { $this->limit_precision = $prec; }
		function getLimitPrecision()   { return $this->limit_precision; }
		function wasLimited()          { return $this->was_limited; }

		function setPlainMode($enable = true) { $this->plain_mode = $enable; }
		function getPlainMode()        { return $this->plain_mode; }

		function setDetectURLs($enable = true) { $this->detect_urls = $enable; }
		function getDetectURLs()       { return $this->detect_urls; }
		function setURLPattern($pattern) { $this->url_pattern = $pattern; }
		function getURLPattern()       { return $this->url_pattern; }

		function setURLTargetable($enable) { $this->url_targetable = $enable; }
		function getURLTargetable()    { return $this->url_targetable; }

		function setURLTarget($target) { $this->url_target = $target; }
		function getURLTarget()        { return $this->url_target; }

		//-----------------------------------------------------------------------------
		// Rule-management:  You can add your own custom tag rules, or use the defaults.
		// These are basically getter/setter functions that exist for convenience.

		function addRule($name, $rule) { $this->tag_rules[$name] = $rule; }
		function removeRule($name)     { unset($this->tag_rules[$name]); }
		function getRule($name)        { return isset($this->tag_rules[$name])
		                                   ? $this->tag_rules[$name] : false; }
		function clearRules()          { $this->tag_rules = []; }
		function getDefaultRule($name) { return isset($this->defaults->default_tag_rules[$name])
		                                   ? $this->defaults->default_tag_rules[$name] : false; }
		function setDefaultRule($name) { if (isset($this->defaults->default_tag_rules[$name]))
		                                    $this->addRule($name, $this->defaults->default_tag_rules[$name]);
		                                 else $this->removeRule($name); }
		function getDefaultRules()     { return $this->defaults->default_tag_rules; }
		function setDefaultRules()     { $this->tag_rules = $this->defaults->default_tag_rules; }

		//-----------------------------------------------------------------------------
		// Handling for [[wiki]] and [[wiki|Wiki]] links and other replaced items.
		// These are basically getter/setter functions that exist for convenience.

		function setWikiURL($url)      { $this->wiki_url = $url; }
		function getWikiURL($url)      { return $this->wiki_url; }
		function getDefaultWikiURL()   { return '/?page='; }

		function setLocalImgDir($path) { $this->local_img_dir = $path; }
		function getLocalImgDir()      { return $this->local_img_dir; }
		function getDefaultLocalImgDir() { return "img"; }
		function setLocalImgURL($path) { $this->local_img_url = $path; }
		function getLocalImgURL()      { return $this->local_img_url; }
		function getDefaultLocalImgURL() { return "img"; }

		function setRuleHTML($html)    { $this->rule_html = $html; }
		function getRuleHTML()         { return $this->rule_html; }
		function getDefaultRuleHTML()  { return "\n<hr class=\"bbcode_rule\" />\n"; }

		//-----------------------------------------------------------------------------
		// Smiley management.  You can use the default smileys, or add your own.
		// These are *mostly* getter/setter functions, but they also affect the
		// caching of the smiley-processing rules.

		function addSmiley($code, $image) { $this->smileys[$code] = $image; $this->smiley_regex = false; }
		function removeSmiley($code)      { unset($this->smileys[$code]); $this->smiley_regex = false; }
		function getSmiley($code)         { return isset($this->smileys[$code])
		                                      ? $this->smileys[$code] : false; }
		function clearSmileys()           { $this->smileys = []; $this->smiley_regex = false; }

		function getDefaultSmiley($code)  { return isset($this->defaults->default_smileys[$code])
		                                      ? $this->defaults->default_smileys[$code] : false; }
		function setDefaultSmiley($code)  { $this->smileys[$code] = @$this->defaults->default_smileys[$code];
		                                     $this->smiley_regex = false; }
		function getDefaultSmileys()      { return $this->defaults->default_smileys; }
		function setDefaultSmileys()      { $this->smileys = $this->defaults->default_smileys;
		                                     $this->smiley_regex = false; }

		function setSmileyDir($path)      { $this->smiley_dir = $path; }
		function getSmileyDir()           { return $this->smiley_dir; }
		function getDefaultSmileyDir()    { return "smileys"; }
		function setSmileyURL($path)      { $this->smiley_url = $path; }
		function getSmileyURL()           { return $this->smiley_url; }
		function getDefaultSmileyURL()    { return "smileys"; }

		function setEnableSmileys($enable = true) { $this->enable_smileys = $enable; }
		function getEnableSmileys()       { return $this->enable_smileys; }

		//-----------------------------------------------------------------------------
		//  Smiley, URL, and HTML-conversion support routines.

		// Like PHP's built-in nl2br, but this one can convert Windows, Un*x, or Mac
		// newlines to a <br />, and regularizes the output to just use Un*x-style
		// newlines to boot.
		function nl2br($string) {
			return preg_replace("/\\x0A|\\x0D|\\x0A\\x0D|\\x0D\\x0A/", "<br />\n", $string);
		}

		// This function comes straight from the PHP documentation on html_entity_decode,
		// and performs exactly the same function.  Unlike html_entity_decode, it
		// works on older versions of PHP (prior to 4.3.0).
		function unHTMLEncode($string) {
			// Use the (faster!) built-in if it's available.
			if (function_exists("html_entity_decode"))
				return html_entity_decode($string);

			// Replace numeric entities.
			$string = preg_replace_callback(
					'/&#x([0-9a-f]+);/i',
					function($matches) {
						return chr(hexdec($matches[1]));
					},
					$string
			);
			$string = preg_replace_callback(
					'/&#([0-9]+);/',
					function($matches) {
						return chr($matches[1]);
					},
					$string
			);

			// Replace literal entities.
			$trans_tbl = get_html_translation_table(HTML_ENTITIES);
			$trans_tbl = array_flip($trans_tbl);

			return strtr($string, $trans_tbl);
		}

		// This takes an arbitrary string and makes it a wiki-safe string:  It converts
		// all characters to be within [a-zA-Z0-9'",.:_-] by converting everything else to
		// _ characters, compacts multiple _ characters together, and trims initial and
		// trailing _ characters.  So, for example, [[Washington, D.C.]] would become
		// "Washington_D.C.", safe to pass through a URL or anywhere else.  All characters
		// in the extended-character range (0x7F-0xFF) will be URL-encoded.
		function wikify($string) {
			return rawurlencode(str_replace(" ", "_",
				trim(preg_replace("/[!?;@#\$%\\^&*<>=+`~\\x00-\\x20_-]+/", " ", $string))));
		}

		// Returns true if the given string is a valid URL.  If $email_too is false,
		// this checks for:
		//
		//    http :// domain [:port] [/] [any single-line string]
		//    https :// domain [:port] [/] [any single-line string]
		//    ftp :// domain [:port] [/] [any single-line string]
		//
		// If $email_too is true (the default), this also allows the mailto protocol:
		//
		//    mailto : name @ domain
		//
		function isValidURL($string, $email_too = true) {
			// Check for anything that uses http, https, or ftp, with the general
			// structure being:  protocol :// domain [:port] [/] [any single-line string]
			// For security reasons, we disallow username/password inclusion in URLs.
			if (preg_match("/^
				(?:https?|ftp):\\/\\/
				(?:
					(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\\.)+
					[a-zA-Z0-9]
					(?:[a-zA-Z0-9-]*[a-zA-Z0-9])?
				|
					\\[
					(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}
					(?:
						25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-zA-Z0-9-]*[a-zA-Z0-9]:
						(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21-\\x5A\\x53-\\x7F]
							|\\\\[\\x01-\\x09\\x0B\\x0C\\x0E-\\x7F])+
					)
					\\]
				)
				(?::[0-9]{1,5})?
				(?:[\\/\\?\\#][^\\n\\r]*)?
				$/Dx", $string)) return true;

			// Check for anything that does *not* have a colon in it before the first
			// slash or question mark or #; that indicates a local file relative to us.
			if (preg_match("/^[^:]+([\\/\\\\?#][^\\r\\n]*)?$/D", $string))
				return true;

			// Match mail addresses.
			if ($email_too)
				if (substr($string, 0, 7) == "mailto:")
					return $this->isValidEmail(substr($string, 7));

			// Reject all other protocols.
			return false;
		}

		// Returns true if the given string is a valid e-mail address.  This allows
		// everything that RFC821 allows, including e-mail addresses that make no sense.
		function isValidEmail($string) {
			$validator = new BBCodeEmailAddressValidator;
			return $validator->check_email_address($string);
		/*
			return preg_match("/^
				(?:
					[a-z0-9\\!\\#\\\$\\%\\&\\'\\*\\+\\/=\\?\\^_`\\{\\|\\}~-]+
					(?:\.[a-z0-9\\!\\#\\\$\\%\\&\\'\\*\\+\\/=\\?\\^_`\\{\\|\\}~-]+)*
				|
					\"(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]
						|\\\\[\\x01-\\x09\\x0B\\x0C\\x0E-\\x7F])*\"
				)
				@
				(?:
					(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+
					[a-z0-9]
					(?:[a-z0-9-]*[a-z0-9])?
				|
					\\[
					(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}
					(?:
						25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:
						(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21-\\x5A\\x53-\\x7F]
							|\\\\[\\x01-\\x09\\x0B\\x0C\\x0E-\\x7F])+
					)
					\\]
				)
				$/Dx", $string);
		*/
		}

		// This function is used to wrap around calls to htmlspecialchars() for
		// plain text so that you can add your own text-evaluation code if you want.
		// For example, you might want to make *foo* turn into <b>foo</b>, or
		// something like that.  The default behavior is just to call htmlspecialchars()
		// and be done with it, but if you inherit and override this function, you
		// can do pretty much anything you want.
		//
		// Note that htmlspecialchars() is still used directly for doing things like
		// cleaning up URLs in tags; this function is applied to *plain* *text* *only*.
		function hTMLEncode($string) {
			if (!$this->allow_ampersand)
				return htmlspecialchars($string);
			else return str_replace(['<', '>', '"'],
					['&lt;', '&gt;', '&quot;'], $string);
		}

		// Go through a string containing plain text and do three things on it:
		// Replace < and > and & and " with HTML-safe equivalents, and replace
		// smileys like :-) with <img /> tags, and replace any embedded URLs
		// with <a href=...>...</a> links.
		function fixupOutput($string) {
			global $BBCode_Profiler;
			$BBCode_Profiler->begin('FixupOutput');

			if ($this->debug)
				print "<b>FixupOutput:</b> input: <tt>" . htmlspecialchars($string) . "</tt><br />\n";

			if (!$this->detect_urls) {
				// Easy case:  No URL-decoding, so don't take the time to do it.
				$BBCode_Profiler->end('FixupOutput');
				$output = $this->internal_ProcessSmileys($string);
				$BBCode_Profiler->begin('FixupOutput');
			}
			else {
				// Extract out any embedded URLs, and then process smileys and such on
				// any text in between them.  This necessarily means that URLs get
				// slightly higher priority than smileys do, although there really
				// shouldn't be any overlap if the user's choices of smileys are at
				// least reasonably intelligent.  (For example, declaring "foo.com"
				// or ":http:" to be smileys will probably not work, since the URL decoder
				// will likely capture those before the smiley decoder ever has a chance
				// at them.  But then you didn't want a smiley named "foo.com" anyway,
				// did you?)
				$chunks = $this->internal_AutoDetectURLs($string);
				$output = [];
				if (count($chunks)) {
					$is_a_url = false;
					foreach ($chunks as $index => $chunk) {
						if (!$is_a_url) {
							$BBCode_Profiler->end('FixupOutput');
							$chunk = $this->internal_ProcessSmileys($chunk);
							$BBCode_Profiler->begin('FixupOutput');
						}
						$output[] = $chunk;
						$is_a_url = !$is_a_url;
					}
				}
				$output = implode("", $output);
			}

			if ($this->debug)
				print "<b>FixupOutput:</b> output: <tt>" . htmlspecialchars($output) . "</tt><br />\n";

			$BBCode_Profiler->end('FixupOutput');

			return $output;
		}

		// Go through a string containing plain text and do two things on it:
		// Replace < and > and & and " with HTML-safe equivalents, and replace
		// smileys like :-) with <img /> tags.
		function internal_ProcessSmileys($string) {
			global $BBCode_Profiler;
			$BBCode_Profiler->begin('ProcessSmileys:other');

			if (!$this->enable_smileys || $this->plain_mode) {
				// If smileys are turned off, don't convert them.
				$output = $this->hTMLEncode($string);
			}
			else {
				// If the smileys need to be computed, process them now.
				if ($this->smiley_regex === false) {
					$BBCode_Profiler->end('ProcessSmileys:other');
					$BBCode_Profiler->begin('ProcessSmileys:rebuild');
					$this->internal_RebuildSmileys();
					$BBCode_Profiler->end('ProcessSmileys:rebuild');
					$BBCode_Profiler->begin('ProcessSmileys:other');
				}

				// Split the string so that it consists of alternating pairs of smileys and non-smileys.
				$BBCode_Profiler->end('ProcessSmileys:other');
				$BBCode_Profiler->begin('ProcessSmileys:split');
				$tokens = preg_split($this->smiley_regex, $string, -1, PREG_SPLIT_DELIM_CAPTURE);
				$BBCode_Profiler->end('ProcessSmileys:split');
				$BBCode_Profiler->begin('ProcessSmileys:other');

				if (count($tokens) <= 1) {
					// Special (common) case:  This skips the smiley constructor if there
					// were no smileys found, which is most of the time.
					$output = $this->hTMLEncode($string);
				}
				else {
					$output = "";
					$is_a_smiley = false;
					foreach ($tokens as $token) {
						if (!$is_a_smiley) {
							// For non-smiley text, we just pass it through htmlspecialchars.
							$output .= $this->hTMLEncode($token);
						}
						else {
							if (isset($this->smiley_info[$token])) {
								// Use cached image-size information, if possible.
								$info = $this->smiley_info[$token];
							}
							else {
								$info = @getimagesize($this->smiley_dir . '/' . $this->smileys[$token]);
								$this->smiley_info[$token] = $info;
							}
							$alt = htmlspecialchars($token);
							$output .= "<img src=\"" . htmlspecialchars($this->smiley_url . '/' . $this->smileys[$token])
								. "\" width=\"{$info[0]}\" height=\"{$info[1]}\""
								. " alt=\"$alt\" title=\"$alt\" class=\"bbcode_smiley\" />";
						}
						$is_a_smiley = !$is_a_smiley;
					}
				}
			}

			$BBCode_Profiler->end('ProcessSmileys:other');

			return $output;
		}

		function internal_RebuildSmileys() {
			// Construct the $this->smiley_regex that can recognize all
			// of the smileys.  This will save us a lot of computation time
			// in $this->parse() if multiple BBCode strings are being
			// processed by the same script.
			$regex = ["/(?<![\\w])("];
			$first = true;
			foreach ($this->smileys as $code => $filename) {
				if (!$first) $regex[] = "|";
				$regex[] = preg_quote("$code", '/');
				$first = false;
			}
			$regex[] = ")(?![\\w])/";
			$this->smiley_regex = implode("", $regex);

			if ($this->debug)
				print "<b>Internal_RebuildSmileys:</b> regex: <tt>" . htmlspecialchars(print_r($regex, true)) . "</tt><br />\n";
		}

		// Search through the input for URLs, or things that are URL-like.  We search
		// for several possibilities here:
		//
		//   First format (HTTP/HTTPS/FTP):
		//      <"http:" or "https:" or "ftp:"> <optional "//"> <domain or IPv4 or IPv6> <optional tail>
		//
		//   Second format (implicit HTTP):
		//      <domain or IPv4> <optional tail>
		//
		//   Third format (e-mail):
		//      <simple username> "@" <domain or IPv4>
		//
		// In short, we look for domains and protocols, and if we find them, we consume any paths
		// or parameters after them, stopping at the first whitespace.
		//
		// We use the same split-and-match technique used by the lexer and the smiley parser,
		// since it's the fastest way to perform tokenization in PHP.
		//
		// Once we find the URL, we convert it according to the rule given in $this->url_pattern.
		//
		// Note that the input string is plain text, not HTML or BBCode.  The return value
		// must be an array of alternating pairs of plain text (even indexes) and HTML (odd indexes).
		function internal_AutoDetectURLs($string) {
			global $BBCode_Profiler;
			$BBCode_Profiler->begin('AutoDetectURLs');

			$output = preg_split("/( (?:
					(?:https?|ftp) : \\/*
					(?:
						(?: (?: [a-zA-Z0-9-]{2,} \\. )+
							(?: arpa | com | org | net | edu | gov | mil | int | [a-z]{2}
								| aero | biz | coop | info | museum | name | pro
								| example | invalid | localhost | test | local | onion | swift ) )
						| (?: [0-9]{1,3} \\. [0-9]{1,3} \\. [0-9]{1,3} \\. [0-9]{1,3} )
						| (?: [0-9A-Fa-f:]+ : [0-9A-Fa-f]{1,4} )
					)
					(?: : [0-9]+ )?
					(?! [a-zA-Z0-9.:-] )
					(?:
						\\/
						[^&?#\\(\\)\\[\\]\\{\\}<>\\'\\\"\\x00-\\x20\\x7F-\\xFF]*
					)?
					(?:
						[?#]
						[^\\(\\)\\[\\]\\{\\}<>\\'\\\"\\x00-\\x20\\x7F-\\xFF]+
					)?
				) | (?:
					(?:
						(?: (?: [a-zA-Z0-9-]{2,} \\. )+
							(?: arpa | com | org | net | edu | gov | mil | int | [a-z]{2}
								| aero | biz | coop | info | museum | name | pro
								| example | invalid | localhost | test | local | onion | swift ) )
						| (?: [0-9]{1,3} \\. [0-9]{1,3} \\. [0-9]{1,3} \\. [0-9]{1,3} )
					)
					(?: : [0-9]+ )?
					(?! [a-zA-Z0-9.:-] )
					(?:
						\\/
						[^&?#\\(\\)\\[\\]\\{\\}<>\\'\\\"\\x00-\\x20\\x7F-\\xFF]*
					)?
					(?:
						[?#]
						[^\\(\\)\\[\\]\\{\\}<>\\'\\\"\\x00-\\x20\\x7F-\\xFF]+
					)?
				) | (?:
					[a-zA-Z0-9._-]{2,} @
					(?:
						(?: (?: [a-zA-Z0-9-]{2,} \\. )+
							(?: arpa | com | org | net | edu | gov | mil | int | [a-z]{2}
								| aero | biz | coop | info | museum | name | pro
								| example | invalid | localhost | test | local | onion | swift ) )
						| (?: [0-9]{1,3} \\. [0-9]{1,3} \\. [0-9]{1,3} \\. [0-9]{1,3} )
					)
				) )/Dx", $string, -1, PREG_SPLIT_DELIM_CAPTURE);

			if (count($output) > 1) {

				$is_a_url = false;
				foreach ($output as $index => $token) {
					if ($is_a_url) {
						// Decide whether we have an e-mail address or a server address.
						if (preg_match("/^[a-zA-Z0-9._-]{2,}@/", $token)) {
							// Plain e-mail address.
							$url = "mailto:" . $token;
						}
						else if (preg_match("/^(https?:|ftp:)\\/*([^\\/&?#]+)\\/*(.*)\$/", $token, $matches)) {
							// Protocol has been provided, so just use it as-is (but fix
							// up any forgotten slashes).
							$url = $matches[1] . '/' . '/' . $matches[2] . "/" . $matches[3];
						}
						else {
							// Raw domain name, like "www.google.com", so convert it for
							// use as an HTTP web address.
							preg_match("/^([^\\/&?#]+)\\/*(.*)\$/", $token, $matches);
							$url = "http:/" . "/" . $matches[1] . "/" . $matches[2];
						}

						// We have a full, complete, and properly-formatted URL, with protocol.
						// Now we need to apply the $this->url_pattern template to turn it into HTML.
						$params = @parse_url($url);
						if (!is_array($params)) $params = [];
						$params['url'] = $url;
						$params['link'] = $url;
						$params['text'] = $token;
						$output[$index] = $this->fillTemplate($this->url_pattern, $params);
					}

					$is_a_url = !$is_a_url;
				}
			}

			$BBCode_Profiler->end('AutoDetectURLs');

			return $output;
		}

		// Fill an HTML template using variable inserts, which look like this:
		//    {$variable}   or   {$variable/flags}   or even   {$myarray.george.father/flags}
		//
		// You may use any variable provided in the parameter array; and you may use the
		// special dot (.) operator to access members of array variables or of object
		// variables.
		//
		// You may add formatting flags to the variable to control how the text parameters
		// are cleaned up.  For example, {$variable/u} causes the variable to be urlencoded().
		// The available flags are:
		//
		//   v - Verbatim.  Do not apply any formatting to the variable; use its exact text,
		//        however the user provided it.  This overrides all other flags.
		//
		//   b - Apply basename().
		//   n - Apply nl2br().
		//   t - Trim.  This causes all initial and trailing whitespace to be trimmed (removed).
		//   w - Clean up whitespace.  This causes all non-newline whitespace, such as
		//        control codes and tabs, to be collapsed into individual space characters.
		//
		//   e - Apply hTMLEncode().
		//   h - Apply htmlspecialchars().
		//   k - Apply wikify().
		//   u - Apply urlencode().
		//
		// Note that only one of the e, h, k, or u "formatting flags" may be specified;
		// these flags are mutually-exclusive.
		function fillTemplate($template, $insert_array, $default_array = []) {
			$pieces = preg_split('/(\{\$[a-zA-Z0-9_.:\/-]+\})/', $template,
				-1, PREG_SPLIT_DELIM_CAPTURE);

			// Special (common) high-speed case:  No inserts found in the template.
			if (count($pieces) <= 1)
				return $template;

			$result = [];

			$is_an_insert = false;
			foreach ($pieces as $piece) {

				if (!$is_an_insert) {
					if ($this->debug) {
						print "<b>FormatInserts:</b> add text: <tt>"
							. htmlspecialchars($piece) . "</tt><br />\n";
					}
					$result[] = $piece;
				}
				else if (!preg_match('/\{\$([a-zA-Z0-9_:-]+)((?:\\.[a-zA-Z0-9_:-]+)*)(?:\/([a-zA-Z0-9_:-]+))?\}/', $piece, $matches)) {
					if ($this->debug) {
						print "<b>FormatInserts:</b> not an insert: add as text: <tt>"
							. htmlspecialchars($piece) . "</tt><br />\n";
					}
					$result[] = $piece;
				}
				else {
					// We have a valid variable name, possibly with an index and some flags.

					// Locate the requested variable in the input parameters.
					if (isset($insert_array[$matches[1]]))
						$value = @$insert_array[$matches[1]];
					else $value = @$default_array[$matches[1]];

					if (strlen(@$matches[2])) {
						// We have one or more indexes, so break them apart and look up the requested data.
						foreach (split(".", substr($matches[2], 1)) as $index) {
							if (is_array($value))
								$value = @$value[$index];
							else if (is_object($value)) {
								$value = (array)$value;
								$value = @$value[$index];
							}
							else $value = "";
						}
					}

					// Make sure the resulting value is a printable string.
					switch (gettype($value)) {
					case 'boolean': $value = $value ? "true" : "false"; break;
					case 'integer': $value = (string)$value; break;
					case 'double': $value = (string)$value; break;
					case 'string': break;
					default: $value = ""; break;
					}

					// See if there are any flags.
					if (strlen(@$matches[3]))
						$flags = array_flip(str_split($matches[3]));
					else $flags = [];

					// If there are flags, process the value according to them.
					if (!isset($flags['v'])) {
						if (isset($flags['w']))
							$value = preg_replace("/[\\x00-\\x09\\x0B-\x0C\x0E-\\x20]+/", " ", $value);
						if      (isset($flags['t'])) $value = trim($value);
						if      (isset($flags['b'])) $value = basename($value);
						if      (isset($flags['e'])) $value = $this->hTMLEncode($value);
						else if (isset($flags['k'])) $value = $this->wikify($value);
						else if (isset($flags['h'])) $value = htmlspecialchars($value);
						else if (isset($flags['u'])) $value = urlencode($value);
						if      (isset($flags['n'])) $value = $this->nl2br($value);
					}

					if ($this->debug) {
						print "<b>FormatInserts:</b> add insert: <tt>" . htmlspecialchars($piece)
							. "</tt> --&gt; <tt>" . htmlspecialchars($value) . "</tt><br />\n";
					}

					// Append the value to the output.
					$result[] = $value;
				}

				$is_an_insert = !$is_an_insert;
			}

			return implode("", $result);
		}

		//-----------------------------------------------------------------------------
		//  Stack and output-management (internal).

		// Collect a series of text strings from a token stack and return them as a
		// single string.  We use output buffering because it seems to produce slightly
		// more efficient string concatenation.
		function internal_CollectText($array, $start = 0) {
			global $BBCode_Profiler;
			$BBCode_Profiler->begin('CollectText');

			ob_start();
			for ($start = intval($start), $end = count($array); $start < $end; $start++)
				print $array[$start][BBCODE_STACK_TEXT];
			$output = ob_get_contents();
			ob_end_clean();

			$BBCode_Profiler->end('CollectText');

			return $output;
		}
		function internal_CollectTextReverse($array, $start = 0, $end = 0) {
			global $BBCode_Profiler;
			$BBCode_Profiler->begin('CollectTextReverse');

			ob_start();
			for ($start = intval($start); $start >= $end; $start--)
				print $array[$start][BBCODE_STACK_TEXT];
			$output = ob_get_contents();
			ob_end_clean();

			$BBCode_Profiler->end('CollectTextReverse');

			return $output;
		}

		// Output everything on the stack from $pos to the top, inclusive, as
		// plain text, and return it.  This is a little more complicated than
		// necessary, because if we encounter end-tag-optional tags in here,
		// they're not to be outputted as plain text:  They're fully legit, and
		// need to be processed with the plain text after them as their body.
		// This returns a list of tokens in the REVERSE of output order.
		function internal_GenerateOutput($pos) {
			global $BBCode_Profiler;
			$BBCode_Profiler->begin('GenerateOutput');

			if ($this->debug) {
				print "<b>Internal_GenerateOutput:</b> from=$pos len=" . (count($this->stack) - $pos)
					. "<br />\n"
					. "<b>Internal_GenerateOutput:</b> Stack contents: <tt>"
					. $this->internal_DumpStack() . "</tt><br />\n";
			}
			$output = [];
			while (count($this->stack) > $pos) {
				$token = array_pop($this->stack);
				if ($token[BBCODE_STACK_TOKEN] != BBCODE_TAG) {
					// Not a tag, so just push it to the output.
					$output[] = $token;
					if ($this->debug) {
						print "<b>Internal_GenerateOutput:</b> push text: <tt>"
							. htmlspecialchars($token[BBCODE_STACK_TEXT]) . "</tt><br />\n";
					}
				}
				else {
					// This is a start tag that is either ending-optional or ending-forgotten.
					// But because of class dependencies, we can't simply reject it; deeper
					// tags may already have been processed on the assumption that this class
					// was valid.  So rather than reject it, as one might expect from
					// 'end_tag' => BBCODE_REQUIRED, we process it so that any deeper tags
					// that require it will still be treated correctly.  This is the only
					// alternative to having to perform two passes over the input, one to validate
					// classes and the other to convert the output:  So we choose speed over
					// precision here, but it's a decision that only affects broken tags anyway.
					$name = @$token[BBCODE_STACK_TAG]['_name'];
					$rule = @$this->tag_rules[$name];
					$end_tag = @$rule['end_tag'];
					if (!isset($rule['end_tag'])) $end_tag = BBCODE_REQUIRED;
					else $end_tag = $rule['end_tag'];
					array_pop($this->start_tags[$name]);	// Remove the locator for this tag.
					if ($end_tag == BBCODE_PROHIBIT) {
						// Broken tag, so just push it to the output as HTML.
						$output[] = [
							BBCODE_STACK_TOKEN => BBCODE_TEXT,
							BBCODE_STACK_TAG => false,
							BBCODE_STACK_TEXT => $token[BBCODE_STACK_TEXT],
							BBCODE_STACK_CLASS => $this->current_class,
						];
						if ($this->debug) {
							print "<b>Internal_GenerateOutput:</b> push broken tag: <tt>"
								. htmlspecialchars($token['text']) . "</tt><br />\n";
						}
					}
					else {
						// Found a start tag where the end tag is optional, or a start
						// tag where the end tag was forgotten, so that tag should be
						// processed with the current output as its content.
						if ($this->debug) {
							print "<b>Internal_GenerateOutput:</b> found start tag with optional end tag: <tt>"
								. htmlspecialchars($token[BBCODE_STACK_TEXT]) . "</tt><br />\n";
						}

						// If this was supposed to have an end tag, and we find a floating one
						// later on, then we should consume it.
						if ($end_tag == BBCODE_REQUIRED)
							@$this->lost_start_tags[$name] += 1;

						$end = $this->internal_CleanupWSByIteratingPointer(@$rule['before_endtag'], 0, $output);
						$this->internal_CleanupWSByPoppingStack(@$rule['after_tag'], $output);
						$tag_body = $this->internal_CollectTextReverse($output, count($output)-1, $end);

						// Note:  We don't process 'after_endtag' because the invisible end tag
						// always butts up against another tag, so there's *never* any whitespace
						// after it.  Attempting to process 'after_endtag' would just be a waste
						// of time because it'd never match.  But 'before_tag' is useful, though.
						$this->internal_CleanupWSByPoppingStack(@$rule['before_tag'], $this->stack);

						if ($this->debug) {
							print "<b>Internal_GenerateOutput:</b> optional-tag's content: <tt>"
								. htmlspecialchars($tag_body) . "</tt><br />\n";
						}

						$this->internal_UpdateParamsForMissingEndTag(@$token[BBCODE_STACK_TAG]);
						$tag_output = $this->doTag(BBCODE_OUTPUT, $name,
							@$token[BBCODE_STACK_TAG]['_default'], @$token[BBCODE_STACK_TAG], $tag_body);

						if ($this->debug) {
							print "<b>Internal_GenerateOutput:</b> push optional-tag's output: <tt>"
								. htmlspecialchars($tag_output) . "</tt><br />\n";
						}

						$output = [[
							BBCODE_STACK_TOKEN => BBCODE_TEXT,
							BBCODE_STACK_TAG => false,
							BBCODE_STACK_TEXT => $tag_output,
							BBCODE_STACK_CLASS => $this->current_class
						]];
					}
				}
			}
			if ($this->debug) {
				print "<b>Internal_GenerateOutput:</b> done; output contains " . count($output) . " items: <tt>"
					. $this->internal_DumpStack($output) . "</tt><br />\n";
				$noutput = $this->internal_CollectTextReverse($output, count($output) - 1);
				print "<b>Internal_GenerateOutput:</b> output: <tt>" . htmlspecialchars($noutput) . "</tt><br />\n";
				print "<b>Internal_GenerateOutput:</b> Stack contents: <tt>" . $this->internal_DumpStack() . "</tt><br />\n";
			}
			$this->internal_ComputeCurrentClass();
			$BBCode_Profiler->end('GenerateOutput');
			return $output;
		}

		// We're transitioning into a class that's not allowed inside the current one
		// (like they tried to put a [center] tag inside a [b] tag), so we need to
		// unwind the stack, outputting content until we're inside a valid state again.
		// To do this, we need to walk back down the stack, searching for a class that's
		// in the given list; when we find one, we output everything above it.  Then we
		// output everything on the stack from the given height to the top, inclusive,
		// and pop everything in that range.  This leaves a BBCODE_TEXT element on the
		// stack that is the fully-outputted version of the content, and its class
		// will be the same as that of the stack element before it (or root_class if there
		// is no element before it).
		//
		// This returns true if the stack could be rewound to a safe state, or false
		// if no such "safe state" existed.
		function internal_RewindToClass($class_list) {
			if ($this->debug) {
				print "<b>Internal_RewindToClass:</b> stack has " . count($this->stack)
					. " items; allowed classes are: <tt>";
				print_r($class_list);
				print "</tt><br />\n";
			}

			// Walk backward from the top of the stack, searching for a state where
			// the new class was still legal.
			$pos = count($this->stack) - 1;
			while ($pos >= 0 && !in_array($this->stack[$pos][BBCODE_STACK_CLASS], $class_list))
				$pos--;
			if ($pos < 0) {
				if (!in_array($this->root_class, $class_list))
					return false;
			}

			if ($this->debug)
				print "<b>Internal_RewindToClass:</b> rewound to " . ($pos+1) . "<br />\n";

			// Convert any tags on the stack from $pos+1 to the top, inclusive, to
			// plain text tokens, possibly processing any tags where the end tags
			// are optional.
			$output = $this->internal_GenerateOutput($pos+1);

			// Push the clean tokens back onto the stack.
			while (count($output)) {
				$token = array_pop($output);
				$token[BBCODE_STACK_CLASS] = $this->current_class;
				$this->stack[] = $token;
			}

			if ($this->debug) {
				print "<b>Internal_RewindToClass:</b> stack has " . count($this->stack)
					. " items now.<br />\n";
			}

			return true;
		}

		// We've found an end tag with the given name, so walk backward until we
		// find the start tag, and then output the contents.
		function internal_FinishTag($tag_name) {
			if ($this->debug) {
				print "<b>Internal_FinishTag:</b> stack has " . count($this->stack)
					. " items; searching for start tag for <tt>[/"
					. htmlspecialchars($tag_name) . "]</tt><br />\n";
			}

			// If this is a malformed tag like [/], tell them now, since there's
			// no way we can possibly match it.
			if (strlen($tag_name) <= 0)
				return false;

			// This is where we *would* walk backward from the top of the stack, searching
			// for the matching start tag for this end tag.  But since we record the
			// locations of start tags in a separate array indexed by tag name, we don't
			// need to search:  We already know where the start tag is.  So we look up
			// the location of the start tag and rewind right to that spot.  This is really
			// only a constant-time speedup, since in the non-degenerate cases,
			// internal_FinishTag() still runs in o(n) time.  (internal_GenerateOutput()
			// runs in o(n) time, and internal_FinishTag() calls it.  But in the degenerate
			// case, where there's an end tag with no start tag, this is significantly
			// faster, for whatever that's worth.)  But even a constant-time speedup is a
			// speedup, so this is overall a win.
			if (isset($this->start_tags[$tag_name])
				&& count($this->start_tags[$tag_name]))
				$pos = array_pop($this->start_tags[$tag_name]);
			else $pos = -1;

			if ($this->debug)
				print "<b>Internal_FinishTag:</b> rewound to " . ($pos+1) . "<br />\n";

			// If there is no matching start tag, then this is a floating (bad)
			// end tag, so tell the caller.
			if ($pos < 0) return false;

			// Okay, we're doing pretty good here.  We need to do whitespace
			// cleanup for after the start tag and before the end tag, though.  We
			// do end-tag cleanup by popping, and we do start-tag cleanup by skipping
			// $pos forward.  (We add one because we've actually rewound the stack
			// to the start tag itself.)
			$newpos = $this->internal_CleanupWSByIteratingPointer(@$this->tag_rules[$tag_name]['after_tag'],
				$pos+1, $this->stack);
			$delta = $newpos - ($pos+1);

			if ($this->debug) {
				print "<b>Internal_FinishTag:</b> whitespace cleanup (rule was \""
					. @$this->tag_rules[$tag_name]['after_tag']
					. "\") moved pointer to " . ($newpos) . "<br />\n";
			}

			// Output everything on the stack from $pos to the top, inclusive, as
			// plain text, and then return it as a string, leaving the start tag on
			// the top of the stack.
			$output = $this->internal_GenerateOutput($newpos);

			// Clean off any whitespace before the end tag that doesn't belong there.
			$newend = $this->internal_CleanupWSByIteratingPointer(@$this->tag_rules[$tag_name]['before_endtag'],
				0, $output);
			$output = $this->internal_CollectTextReverse($output, count($output) - 1, $newend);

			if ($this->debug)
				print "<b>Internal_FinishTag:</b> whitespace cleanup: popping $delta items<br />\n";

			// Clean up any 'after_tag' whitespace we skipped.
			while ($delta-- > 0)
				array_pop($this->stack);
			$this->internal_ComputeCurrentClass();

			if ($this->debug)
				print "<b>Internal_FinishTag:</b> output: <tt>" . htmlspecialchars($output) . "</tt><br />\n";

			return $output;
		}

		// Recompute the current class, based on the class of the stack's top element.
		function internal_ComputeCurrentClass() {
			if (count($this->stack) > 0)
				$this->current_class = $this->stack[count($this->stack)-1][BBCODE_STACK_CLASS];
			else $this->current_class = $this->root_class;
			if ($this->debug) {
				print "<b>Internal_ComputeCurrentClass:</b> current class is now \"<tt>"
					. htmlspecialchars($this->current_class) . "</tt>\"<br />\n";
			}
		}

		// Given a stack of tokens in $array, write it to a string (possibly with HTML
		// color and style encodings for readability, if $raw is false).
		function internal_DumpStack($array = false, $raw = false) {
			if (!$raw) $string = "<span style='color: #00C;'>";
			else $string = "";
			if ($array === false)
				$array = $this->stack;
			foreach ($array as $item) {
				switch (@$item[BBCODE_STACK_TOKEN]) {
				case BBCODE_TEXT:
					$string .= "\"" . htmlspecialchars(@$item[BBCODE_STACK_TEXT]) . "\" ";
					break;
				case BBCODE_WS:
					$string .= "WS ";
					break;
				case BBCODE_NL:
					$string .= "NL ";
					break;
				case BBCODE_TAG:
					$string .= "[" . htmlspecialchars(@$item[BBCODE_STACK_TAG]['_name']) . "] ";
					break;
				default:
					$string .= "unknown ";
					break;
				}
			}
			if (!$raw) $string .= "</span>";
			return $string;
		}

		//-----------------------------------------------------------------------------
		//  Whitespace cleanup routines (internal).

		// Walk down from the top of the stack, and remove whitespace/newline tokens from
		// the top according to the rules in the given pattern.
		function internal_CleanupWSByPoppingStack($pattern, &$array) {
			if ($this->debug) {
				print "<b>Internal_CleanupWSByPoppingStack:</b> array has " . count($array)
					. " items; pattern=\"<tt>"
					. htmlspecialchars($pattern) . "</tt>\"<br />\n";
			}

			if (strlen($pattern) <= 0) return;

			$oldlen = count($array);
			foreach (str_split($pattern) as $char) {
				switch ($char) {
				case 's':
					while (count($array) > 0 && $array[count($array)-1][BBCODE_STACK_TOKEN] == BBCODE_WS)
						array_pop($array);
					break;
				case 'n':
					if (count($array) > 0 && $array[count($array)-1][BBCODE_STACK_TOKEN] == BBCODE_NL)
						array_pop($array);
					break;
				case 'a':
					while (count($array) > 0
						&& (($token = $array[count($array)-1][BBCODE_STACK_TOKEN]) == BBCODE_WS
							|| $token == BBCODE_NL))
						array_pop($array);
					break;
				}
			}

			if ($this->debug) {
				print "<b>Internal_CleanupWSByPoppingStack:</b> array now has " . count($array) . " items<br />\n";
				print "<b>Internal_CleanupWSByPoppingStack:</b> array: <tt>" . $this->internal_DumpStack($array)
					. "</tt><br />\n";
			}

			if (count($array) != $oldlen) {
				// We only recompute the class if something actually changed.
				$this->internal_ComputeCurrentClass();
			}
		}

		// Read tokens from the input, and remove whitespace/newline tokens from the input
		// according to the rules in the given pattern.
		function internal_CleanupWSByEatingInput($pattern) {
			global $BBCode_Profiler;

			if ($this->debug) {
				$ptr = $this->lexer->ptr;
				print "<b>Internal_CleanupWSByEatingInput:</b> input pointer is at $ptr; pattern=\"<tt>"
					. htmlspecialchars($pattern) . "</tt>\"<br />\n";
			}

			if (strlen($pattern) <= 0) return;

			foreach (str_split($pattern) as $char) {
				switch ($char) {
				case 's':
					$BBCode_Profiler->begin('Lexer:NextToken');
					$token_type = $this->lexer->nextToken();
					$BBCode_Profiler->end('Lexer:NextToken');
					while ($token_type == BBCODE_WS) {
						$BBCode_Profiler->begin('Lexer:NextToken');
						$token_type = $this->lexer->nextToken();
						$BBCode_Profiler->end('Lexer:NextToken');
					}
					$this->lexer->ungetToken();
					break;
				case 'n':
					$BBCode_Profiler->begin('Lexer:NextToken');
					$token_type = $this->lexer->nextToken();
					$BBCode_Profiler->end('Lexer:NextToken');
					if ($token_type != BBCODE_NL)
						$this->lexer->ungetToken();
					break;
				case 'a':
					$BBCode_Profiler->begin('Lexer:NextToken');
					$token_type = $this->lexer->nextToken();
					$BBCode_Profiler->end('Lexer:NextToken');
					while ($token_type == BBCODE_WS || $token_type == BBCODE_NL) {
						$BBCode_Profiler->begin('Lexer:NextToken');
						$token_type = $this->lexer->nextToken();
						$BBCode_Profiler->end('Lexer:NextToken');
					}
					$this->lexer->ungetToken();
					break;
				}
			}

			if ($this->debug)
				print "<b>Internal_CleanupWSByEatingInput:</b> input pointer is now at {$this->lexer->ptr}<br />\n";
		}

		// Read tokens from the given position in the stack, going forward as we match
		// the rules in the given pattern.  Returns the first position *after* the pattern.
		function internal_CleanupWSByIteratingPointer($pattern, $pos, $array) {
			if ($this->debug) {
				print "<b>Internal_CleanupWSByIteratingPointer:</b> pointer is $pos; pattern=\"<tt>"
					. htmlspecialchars($pattern) . "</tt>\"<br />\n";
			}

			if (strlen($pattern) <= 0) return $pos;

			foreach (str_split($pattern) as $char) {
				switch ($char) {
				case 's':
					while ($pos < count($array) && $array[$pos][BBCODE_STACK_TOKEN] == BBCODE_WS)
						$pos++;
					break;
				case 'n':
					if ($pos < count($array) && $array[$pos][BBCODE_STACK_TOKEN] == BBCODE_NL)
						$pos++;
					break;
				case 'a':
					while ($pos < count($array)
						&& (($token = $array[$pos][BBCODE_STACK_TOKEN]) == BBCODE_WS || $token == BBCODE_NL))
						$pos++;
					break;
				}
			}
			if ($this->debug) {
				print "<b>Internal_CleanupWSByIteratingPointer:</b> pointer is now $pos<br />\n"
					. "<b>Internal_CleanupWSByIteratingPointer:</b> array: <tt>"
					. $this->internal_DumpStack($array) . "</tt><br />\n";
			}
			return $pos;
		}

		// We have a string that's too long, so chop it off at a suitable break so that it's
		// no longer than $limit characters, if at all possible (if there's nowhere to break
		// before that, we just chop at $limit).
		function internal_LimitText($string, $limit) {
			if ($this->debug) {
				print "<b>Internal_LimitText:</b> chopping string of length "
					. strlen($string) . " at $limit.<br />\n";
			}
			$chunks = preg_split("/([\\x00-\\x20]+)/", $string, -1, PREG_SPLIT_DELIM_CAPTURE);
			$output = "";
			foreach ($chunks as $chunk) {
				if (strlen($output) + strlen($chunk) > $limit)
					break;
				$output .= $chunk;
			}
			$output = rtrim($output);
			if ($this->debug)
				print "<b>Internal_LimitText:</b> resulting string is length " . strlen($output) . ".<br />\n";
			return $output;
		}

		// If we've reached the text limit, clean up the stack, push the limit tail,
		// set the we-hit-the-limit flag, and return.
		function internal_DoLimit() {
			$this->internal_CleanupWSByPoppingStack("a", $this->stack);

			if (strlen($this->limit_tail) > 0) {
				$this->stack[] = [
					BBCODE_STACK_TOKEN => BBCODE_TEXT,
					BBCODE_STACK_TEXT => $this->limit_tail,
					BBCODE_STACK_TAG => false,
					BBCODE_STACK_CLASS => $this->current_class,
				];
			}

			$this->was_limited = true;
		}

		//-----------------------------------------------------------------------------
		//  Tag evaluation logic (internal).

		// Process a tag:
		//
		//   $action is one of BBCODE_CHECK or BBCODE_OUTPUT.  During BBCODE_CHECK, $contents
		//        will *always* be the empty string, and this function should return true if
		//        the tag is legal based on the available information; or it should return
		//        false if the tag is illegal.  During BBCODE_OUTPUT, $contents will always
		//        be valid, and this function should return HTML.
		//
		//   $tag_name is the name of the tag being processed.
		//
		//   $default_value is the default value given; for example, in [url=foo], it's "foo".
		//        This value has NOT been passed through htmlspecialchars().
		//
		//   $params is an array of key => value parameters associated with the tag; for example,
		//        in [smiley src=smile alt=:-)], it's array('src' => "smile", 'alt' => ":-)").
		//        These keys and values have NOT beel passed through htmlspecialchars().
		//
		//   $contents is the body of the tag during BBCODE_OUTPUT.  For example, in
		//        [b]Hello[/b], it's "Hello".  THIS VALUE IS ALWAYS HTML, not BBCode.
		//
		// For BBCODE_CHECK, this must return true (if the tag definition is valid) or false
		// (if the tag definition is not valid); for BBCODE_OUTPUT, this function must return
		// HTML output.
		function doTag($action, $tag_name, $default_value, $params, $contents) {
			$tag_rule = @$this->tag_rules[$tag_name];

			switch ($action) {

			case BBCODE_CHECK:
				if ($this->debug)
					print "<b>DoTag:</b> check tag <tt>[" . htmlspecialchars($tag_name) . "]</tt><br />\n";

				if (isset($tag_rule['allow'])) {
					// An 'allow' array, if given, overrides the other check techniques.
					foreach ($tag_rule['allow'] as $param => $pattern) {
						if ($param == '_content') $value = $contents;
						else if ($param == '_defaultcontent') {
							if (strlen($default_value))
								$value = $default_value;
							else $value = $contents;
						}
						else {
							if (isset($params[$param]))
								$value = $params[$param];
							else $value = @$tag_rule['default'][$param];
						}
						if ($this->debug) {
							print "<b>DoTag:</b> check parameter <tt>\"" . htmlspecialchars($param)
								. "\"</tt>, value <tt>\"" . htmlspecialchars($value) . "\", against \""
								. htmlspecialchars($pattern) . "\"</tt><br />\n";
						}
						if (!preg_match($pattern, $value)) {
							if ($this->debug) {
								print "<b>DoTag:</b> parameter <tt>\"" . htmlspecialchars($param)
									. "\"</tt> failed 'allow' check.<br />\n";
							}
							return false;
						}
					}
					return true;
				}

				switch (@$tag_rule['mode']) {

				default:
				case BBCODE_MODE_SIMPLE:
					$result = true;
					break;

				case BBCODE_MODE_ENHANCED:
					$result = true;
					break;

				case BBCODE_MODE_INTERNAL:
					$result = @call_user_func([$this, @$tag_rule['method']], BBCODE_CHECK,
						$tag_name, $default_value, $params, $contents);
					break;

				case BBCODE_MODE_LIBRARY:
					$result = @call_user_func([$this->defaults, @$tag_rule['method']], $this, BBCODE_CHECK,
						$tag_name, $default_value, $params, $contents);
					break;

				case BBCODE_MODE_CALLBACK:
					$result = @call_user_func(@$tag_rule['method'], $this, BBCODE_CHECK,
						$tag_name, $default_value, $params, $contents);
					break;
				}

				if ($this->debug) {
					print "<b>DoTag:</b> tag <tt>[" . htmlspecialchars($tag_name) . "]</tt> returned "
						. ($result ? "true" : "false") . "<br />\n";
				}

				return $result;

			case BBCODE_OUTPUT:
				if ($this->debug) {
					print "<b>DoTag:</b> output tag <tt>[" . htmlspecialchars($tag_name)
						. "]</tt>: contents=<tt>"
						. htmlspecialchars($contents) . "</tt><br />\n";
				}

				if ($this->plain_mode) {
					// In plain mode, we ignore the tag rules almost entirely, using just
					// the 'plain_start' and 'plain_end' before the content specified in
					// the 'plain_content' member.
					if (!isset($tag_rule['plain_content']))
						$plain_content = ['_content'];
					else $plain_content = $tag_rule['plain_content'];

					// Find the requested content, in the order specified.
					$result = $possible_content = "";
					foreach ($plain_content as $possible_content) {
						if ($possible_content == '_content'
							&& strlen($contents) > 0) {
							$result = $contents;
							break;
						}
						if (isset($params[$possible_content])
							&& strlen($params[$possible_content]) > 0) {
							$result = htmlspecialchars($params[$possible_content]);
							break;
						}
					}

					if ($this->debug) {
						$content_list = "";
						foreach ($plain_content as $possible_content)
							$content_list .= htmlspecialchars($possible_content) . ",";
						print "<b>DoTag:</b> plain-mode tag; possible contents were ($content_list); using \""
							. htmlspecialchars($possible_content) . "\"<br />\n";
					}

					$start = @$tag_rule['plain_start'];
					$end = @$tag_rule['plain_end'];

					// If this is a link tag, figure out its target.
					if (isset($tag_rule['plain_link'])) {
						// Find the requested link target, in the order specified.
						$link = $possible_content = "";
						foreach ($tag_rule['plain_link'] as $possible_content) {
							if ($possible_content == '_content'
								&& strlen($contents) > 0) {
								$link = $this->unHTMLEncode(strip_tags($contents));
								break;
							}
							if (isset($params[$possible_content])
								&& strlen($params[$possible_content]) > 0) {
								$link = $params[$possible_content];
								break;
							}
						}
						$params = @parse_url($link);
						if (!is_array($params)) $params = [];
						$params['link'] = $link;
						$params['url'] = $link;
						$start = $this->fillTemplate($start, $params);
						$end = $this->fillTemplate($end, $params);
					}

					// Construct the plain output using the available content.
					return $start . $result . $end;
				}

				switch (@$tag_rule['mode']) {

				default:
				case BBCODE_MODE_SIMPLE:
					$result = @$tag_rule['simple_start'] . $contents . @$tag_rule['simple_end'];
					break;

				case BBCODE_MODE_ENHANCED:
					$result = $this->internal_DoEnhancedTag($tag_rule, $params, $contents);
					break;

				case BBCODE_MODE_INTERNAL:
					$result = @call_user_func([$this, @$tag_rule['method']], BBCODE_OUTPUT,
						$tag_name, $default_value, $params, $contents);
					break;

				case BBCODE_MODE_LIBRARY:
					$result = @call_user_func([$this->defaults, @$tag_rule['method']], $this, BBCODE_OUTPUT,
						$tag_name, $default_value, $params, $contents);
					break;

				case BBCODE_MODE_CALLBACK:
					$result = @call_user_func(@$tag_rule['method'], $this, BBCODE_OUTPUT,
						$tag_name, $default_value, $params, $contents);
					break;
				}

				if ($this->debug) {
					print "<b>DoTag:</b> output tag <tt>[" . htmlspecialchars($tag_name)
						. "]</tt>: result=<tt>" . htmlspecialchars($result) . "</tt><br />\n";
				}

				return $result;

			default:
				if ($this->debug)
					print "<b>DoTag:</b> unknown action $action requested.<br />\n";

				return false;
			}
		}

		// Format an enhanced tag, which is like a simple tag but uses a short HTML template
		// for its formatting instead.
		//
		// The variables you may use are the parameters of the tag, and '_default' for its
		// default value, '_name' for its name, and '_content' for its contents (body).
		// Note that in enhanced mode, the tag parameters' keys must match [a-zA-Z0-9_:-]+,
		// that is, alphanumeric, with underscore, colon, or hyphen.
		function internal_DoEnhancedTag($tag_rule, $params, $contents) {

			// Set up the special "_content" and "_defaultcontent" parameters.
			$params['_content'] = $contents;
			$params['_defaultcontent'] = strlen(@$params['_default']) ? $params['_default'] : $contents;

			// Now use common template-formatting logic.
			return $this->fillTemplate(@$tag_rule['template'], $params, @$tag_rule['default']);
		}

		//-----------------------------------------------------------------------------
		//  Parser token-processing routines (internal).

		// If an end-tag is required/optional but missing, we simulate it here so that the
		// rule handlers still see a valid '_endtag' parameter.  This way, all rules always
		// see valid '_endtag' parameters except for rules for isolated tags.
		function internal_UpdateParamsForMissingEndTag(&$params) {
			switch ($this->tag_marker) {
			case '[': $tail_marker = ']'; break;
			case '<': $tail_marker = '>'; break;
			case '{': $tail_marker = '}'; break;
			case '(': $tail_marker = ')'; break;
			default: $tail_marker = $this->tag_marker; break;
			}
			$params['_endtag'] = $this->tag_marker . '/' . $params['_name'] . $tail_marker;
		}

		// Process an isolated tag, a tag that is not allowed to have an end tag.
		function internal_ProcessIsolatedTag($tag_name, $tag_params, $tag_rule) {
			if ($this->debug) {
				print "<b>ProcessIsolatedTag:</b> tag <tt>[" . htmlspecialchars($tag_name)
					. "]</tt> is isolated: no end tag allowed, so processing immediately.<br />\n";
			}

			// Ask this tag if its attributes are valid; this gives the tag
			// the option to say, no, I'm broken, don't try to process me.
			if (!$this->doTag(BBCODE_CHECK, $tag_name, @$tag_params['_default'], $tag_params, "")) {
				if ($this->debug) {
					print "<b>ProcessIsolatedTag:</b> isolated tag <tt>[" . htmlspecialchars($tag_name)
						. "]</tt> rejected its parameters; outputting as text after fixup.<br />\n";
				}
				$this->stack[] = [
					BBCODE_STACK_TOKEN => BBCODE_TEXT,
					BBCODE_STACK_TEXT => $this->fixupOutput($this->lexer->text),
					BBCODE_STACK_TAG => false,
					BBCODE_STACK_CLASS => $this->current_class,
				];
				return;
			}

			$this->internal_CleanupWSByPoppingStack(@$tag_rule['before_tag'], $this->stack);
			$output = $this->doTag(BBCODE_OUTPUT, $tag_name, @$tag_params['_default'], $tag_params, "");
			$this->internal_CleanupWSByEatingInput(@$tag_rule['after_tag']);

			if ($this->debug) {
				print "<b>ProcessIsolatedTag:</b> isolated tag <tt>[" . htmlspecialchars($tag_name)
					. "]</tt> is done; pushing its output: <tt>" . htmlspecialchars($output) . "</tt><br />\n";
			}

			$this->stack[] = [
				BBCODE_STACK_TOKEN => BBCODE_TEXT,
				BBCODE_STACK_TEXT => $output,
				BBCODE_STACK_TAG => false,
				BBCODE_STACK_CLASS => $this->current_class,
			];
		}

		// Process a verbatim tag, a tag whose contents (body) must not be processed at all.
		function internal_ProcessVerbatimTag($tag_name, $tag_params, $tag_rule) {

			// This tag is a special type that disallows all other formatting
			// tags within it and wants its contents reproduced verbatim until
			// its matching end tag.  We save the state of the lexer in case
			// we can't find an end tag, in which case we'll have to reject the
			// start tag as broken.
			$state = $this->lexer->saveState();

			$end_tag = $this->lexer->tagmarker . "/" . $tag_name . $this->lexer->end_tagmarker;

			if ($this->debug) {
				print "<b>Internal_ProcessVerbatimTag:</b> tag <tt>[" . htmlspecialchars($tag_name)
					. "]</tt> uses verbatim content: searching for $end_tag...<br />\n";
			}

			// Push tokens until we find a matching end tag or end-of-input.
			$start = count($this->stack);
			$this->lexer->verbatim = true;
			while (($token_type = $this->lexer->nextToken()) != BBCODE_EOI) {
				if ($this->lexer->text == $end_tag) {
					// Found the end tag, so we're done.
					$end_tag_params = $this->lexer->tag;
					break;
				}
				if ($this->debug) {
					print "<b>Internal_ProcessVerbatimTag:</b> push: <tt>"
						. htmlspecialchars($this->lexer->text) . "</tt><br />\n";
				}

				// If this token pushes us past the output limit, split it up on a whitespace
				// boundary, add as much as we can, and then abort.
				if ($this->output_limit > 0
					&& $this->text_length + strlen($this->lexer->text) >= $this->output_limit) {
					$text = $this->internal_LimitText($this->lexer->text,
						$this->output_limit - $this->text_length);
					if (strlen($text) > 0) {
						$this->text_length += strlen($text);
						$this->stack[] = [
							BBCODE_STACK_TOKEN => BBCODE_TEXT,
							BBCODE_STACK_TEXT => $this->fixupOutput($text),
							BBCODE_STACK_TAG => false,
							BBCODE_STACK_CLASS => $this->current_class,
						];
					}
					$this->internal_DoLimit();
					break;
				}
				$this->text_length += strlen($this->lexer->text);

				$this->stack[] = [
					BBCODE_STACK_TOKEN => $token_type,
					BBCODE_STACK_TEXT => htmlspecialchars($this->lexer->text),
					BBCODE_STACK_TAG => $this->lexer->tag,
					BBCODE_STACK_CLASS => $this->current_class,
				];
			}
			$this->lexer->verbatim = false;

			// We've collected a bunch of text for this tag.  Now, make sure it ended on
			// a valid end tag.
			if ($token_type == BBCODE_EOI) {
				// No end tag, so we have to reject the start tag as broken, and
				// rewind the input back to where it was still sane.
				if ($this->debug) {
					print "<b>Internal_ProcessVerbatimTag:</b> no end tag; reached EOI, so rewind"
						. " and push start tag as text after fixup.<br />\n";
				}
				$this->lexer->restoreState($state);
				$this->stack[] = [
					BBCODE_STACK_TOKEN => BBCODE_TEXT,
					BBCODE_STACK_TEXT => $this->fixupOutput($this->lexer->text),
					BBCODE_STACK_TAG => false,
					BBCODE_STACK_CLASS => $this->current_class,
				];
				return;
			}

			if ($this->debug)
				print "<b>Internal_ProcessVerbatimTag:</b> found end tag.<br />\n";

			// Clean up whitespace everywhere except before the start tag.
			$newstart = $this->internal_CleanupWSByIteratingPointer(@$tag_rule['after_tag'], $start, $this->stack);
			$this->internal_CleanupWSByPoppingStack(@$tag_rule['before_endtag'], $this->stack);
			$this->internal_CleanupWSByEatingInput(@$tag_rule['after_endtag']);

			// Collect the output from $newstart to the top of the stack, and then
			// quickly pop off all of those tokens.
			$content = $this->internal_CollectText($this->stack, $newstart);
			if ($this->debug) {
				print "<b>Internal_ProcessVerbatimTag:</b> removing stack elements starting at $start (stack has "
					. count($this->stack) . " elements).<br />\n";
			}
			array_splice($this->stack, $start);
			$this->internal_ComputeCurrentClass();

			// Clean up whitespace before the start tag (the tag was never pushed
			// onto the stack itself, so we don't need to remove it).
			$this->internal_CleanupWSByPoppingStack(@$tag_rule['before_tag'], $this->stack);

			// Found the end tag, so process this tag immediately with
			// the contents collected between them.  Note that we do NOT
			// pass the contents through htmlspecialchars or FixupOutput
			// or anything else that could sanitize it:  They asked for
			// verbatim contents, so they're going to get it.
			$tag_params['_endtag'] = $end_tag_params['_tag'];
			$tag_params['_hasend'] = true;
			$output = $this->doTag(BBCODE_OUTPUT, $tag_name,
				@$tag_params['_default'], $tag_params, $content);

			if ($this->debug) {
				print "<b>Internal_ProcessVerbatimTag:</b> end of verbatim <tt>[" . htmlspecialchars($tag_name)
					. "]</tt> tag processing; push output as text: <tt>" . htmlspecialchars($output)
					. "</tt><br />\n";
			}

			$this->stack[] = [
				BBCODE_STACK_TOKEN => BBCODE_TEXT,
				BBCODE_STACK_TEXT => $output,
				BBCODE_STACK_TAG => false,
				BBCODE_STACK_CLASS => $this->current_class,
			];
		}

		// Called when the parser has read a BBCODE_TAG token.
		function internal_ParseStartTagToken() {

			// Tags are somewhat complicated, because they have to do several things
			// all at once.  First, let's look up what we know about the tag we've
			// encountered.
			$tag_params = $this->lexer->tag;
			$tag_name = @$tag_params['_name'];
			if ($this->debug) {
				print "<hr />\n<b>Internal_ParseStartTagToken:</b> got tag <tt>["
					. htmlspecialchars($tag_name) . "]</tt>.<br />\n";
			}

			// Make sure this tag has been defined.
			if (!isset($this->tag_rules[$tag_name])) {
				if ($this->debug) {
					print "<b>Internal_ParseStartTagToken:</b> tag <tt>[" . htmlspecialchars($tag_name)
						. "]</tt> does not exist; pushing as text after fixup.<br />\n";
				}
				// If there is no such tag with this name, then just push the text as
				// though it was plain text.
				$this->stack[] = [
					BBCODE_STACK_TOKEN => BBCODE_TEXT,
					BBCODE_STACK_TEXT => $this->fixupOutput($this->lexer->text),
					BBCODE_STACK_TAG => false,
					BBCODE_STACK_CLASS => $this->current_class,
				];
				return;
			}
			$tag_rule = $this->tag_rules[$tag_name];

			// We've got a known tag.  See if it's valid inside this class; for example,
			// it's legal to put an inline tag inside a block tag, but not legal to put a
			// block tag inside an inline tag.
			$allow_in = is_array($tag_rule['allow_in'])
				? $tag_rule['allow_in'] : [$this->root_class];
			if (!in_array($this->current_class, $allow_in)) {
				// Not allowed.  Rewind the stack backward until it is allowed.
				if ($this->debug) {
					print "<b>Internal_ParseStartTagToken:</b> tag <tt>[" . htmlspecialchars($tag_name)
						. "]</tt> is disallowed inside class <tt>" . htmlspecialchars($this->current_class)
						. "</tt>; rewinding stack to a safe class.<br />\n";
				}
				if (!$this->internal_RewindToClass($allow_in)) {
					if ($this->debug) {
						print "<b>Internal_ParseStartTagToken:</b> no safe class exists; rejecting"
							. " this tag as text after fixup.<br />\n";
					}
					$this->stack[] = [
						BBCODE_STACK_TOKEN => BBCODE_TEXT,
						BBCODE_STACK_TEXT => $this->fixupOutput($this->lexer->text),
						BBCODE_STACK_TAG => false,
						BBCODE_STACK_CLASS => $this->current_class,
					];
					return;
				}
			}

			// Okay, this tag is allowed (in theory).  Now we need to see whether it's
			// a tag that requires an end tag, or whether it's end-tag-optional, or whether
			// it's end-tag-prohibited.  If it's end-tag-prohibited, then we process it
			// right now (no content); otherwise, we push it onto the stack to defer
			// processing it until either its end tag is encountered or we reach EOI.
			$end_tag = isset($tag_rule['end_tag']) ? $tag_rule['end_tag'] : BBCODE_REQUIRED;

			if ($end_tag == BBCODE_PROHIBIT) {
				// No end tag, so process this tag RIGHT NOW.
				$this->internal_ProcessIsolatedTag($tag_name, $tag_params, $tag_rule);
				return;
			}

			// This tag has a BBCODE_REQUIRED or BBCODE_OPTIONAL end tag, so we have to
			// push this tag on the stack and defer its processing until we see its end tag.

			if ($this->debug) {
				print "<b>Internal_ParseStartTagToken:</b> tag <tt>[" . htmlspecialchars($tag_name)
					. "]</tt> is allowed to have an end tag.<br />\n";
			}

			// Ask this tag if its attributes are valid; this gives the tag the option
			// to say, no, I'm broken, don't try to process me.
			if (!$this->doTag(BBCODE_CHECK, $tag_name, @$tag_params['_default'], $tag_params, "")) {
				if ($this->debug) {
					print "<b>Internal_ParseStartTagToken:</b> tag <tt>[" . htmlspecialchars($tag_name)
						. "]</tt> rejected its parameters; outputting as text after fixup.<br />\n";
				}
				$this->stack[] = [
					BBCODE_STACK_TOKEN => BBCODE_TEXT,
					BBCODE_STACK_TEXT => $this->fixupOutput($this->lexer->text),
					BBCODE_STACK_TAG => false,
					BBCODE_STACK_CLASS => $this->current_class,
				];
				return;
			}

			if (@$tag_rule['content'] == BBCODE_VERBATIM) {
				// Verbatim tags have to be handled specially, since they consume successive
				// input immediately.
				$this->internal_ProcessVerbatimTag($tag_name, $tag_params, $tag_rule);
				return;
			}

			// This is a normal tag that has (or may have) an end tag, so just
			// push it onto the stack and wait for the end tag or the output
			// generator to clean it up.  The act of pushing this causes us to
			// switch to its class.
			if (isset($tag_rule['class']))
				$newclass = $tag_rule['class'];
			else $newclass = $this->root_class;

			if ($this->debug) {
				print "<b>Internal_ParseStartTagToken:</b> pushing tag <tt>[" . htmlspecialchars($tag_name)
					. "]</tt> onto stack; switching to class <tt>" . htmlspecialchars($newclass)
					. "</tt>.<br />\n";
			}

			$this->stack[] = [
				BBCODE_STACK_TOKEN => $this->lexer->token,
				BBCODE_STACK_TEXT => $this->fixupOutput($this->lexer->text),
				BBCODE_STACK_TAG => $this->lexer->tag,
				BBCODE_STACK_CLASS => ($this->current_class = $newclass),
			];

			if (!isset($this->start_tags[$tag_name]))
				$this->start_tags[$tag_name] = [count($this->stack)-1];
			else $this->start_tags[$tag_name][] = count($this->stack)-1;
		}

		// Called when the parser has read a BBCODE_ENDTAG token.
		function internal_ParseEndTagToken() {

			$tag_params = $this->lexer->tag;
			$tag_name = @$tag_params['_name'];
			if ($this->debug) {
				print "<hr />\n<b>Internal_ParseEndTagToken:</b> got end tag <tt>[/"
					. htmlspecialchars($tag_name) . "]</tt>.<br />\n";
			}

			// Got an end tag.  Walk down the stack and see if there's a matching
			// start tag for it anywhere.  If we find one, we pack everything between
			// them as output HTML, and then have the tag format itself with that
			// content.
			$contents = $this->internal_FinishTag($tag_name);
			if ($contents === false) {
				// There's no start tag for this --- unless there was and it was in a bad
				// place.  If there's a start tag we can't reach, then swallow this end tag;
				// otherwise, just output this end tag itself as plain text.
				if ($this->debug) {
					print "<b>Internal_ParseEndTagToken:</b> no start tag for <tt>[/"
						. htmlspecialchars($tag_name) . "]</tt>; push as text after fixup.<br />\n";
				}
				if (@$this->lost_start_tags[$tag_name] > 0) {
					$this->lost_start_tags[$tag_name]--;
				}
				else {
					$this->stack[] = [
						BBCODE_STACK_TOKEN => BBCODE_TEXT,
						BBCODE_STACK_TEXT => $this->fixupOutput($this->lexer->text),
						BBCODE_STACK_TAG => false,
						BBCODE_STACK_CLASS => $this->current_class,
					];
				}
				return;
			}

			// Found a start tag for this, so pop it off the stack, then process the
			// tag, and push the result back onto the stack as plain HTML.
			// We don't need to run a BBCODE_CHECK on the start tag, because it was already
			// done when the tag was pushed onto the stack.
			$start_tag_node = array_pop($this->stack);
			$start_tag_params = $start_tag_node[BBCODE_STACK_TAG];
			$this->internal_ComputeCurrentClass();

			$this->internal_CleanupWSByPoppingStack(@$this->tag_rules[$tag_name]['before_tag'], $this->stack);
			$start_tag_params['_endtag'] = $tag_params['_tag'];
			$start_tag_params['_hasend'] = true;
			$output = $this->doTag(BBCODE_OUTPUT, $tag_name, @$start_tag_params['_default'],
				$start_tag_params, $contents);
			$this->internal_CleanupWSByEatingInput(@$this->tag_rules[$tag_name]['after_endtag']);

			if ($this->debug) {
				print "<b>Internal_ParseEndTagToken:</b> end tag <tt>[/"
					. htmlspecialchars($tag_name) . "]</tt> done; push output: <tt>"
					. htmlspecialchars($output) . "</tt><br />\n";
			}

			$this->stack[] = [
				BBCODE_STACK_TOKEN => BBCODE_TEXT,
				BBCODE_STACK_TEXT => $output,
				BBCODE_STACK_TAG => false,
				BBCODE_STACK_CLASS => $this->current_class,
			];
		}

		//-----------------------------------------------------------------------------
		//  Core parser.  This is where all the magic begins and ends.

		// Core parsing routine.  Call with a BBCode string, and it returns an HTML string.
		function parse($string) {
			global $BBCode_Profiler;

			$BBCode_Profiler = new BBCode_Profiler;
			$BBCode_Profiler->begin('_Parse');

			if ($this->debug) {
				print "<b>Parse Begin:</b> input string is " . strlen($string) . " characters long:<br />\n"
					. "<b>Parse:</b> input: <tt>" . htmlspecialchars(addcslashes($string, "\x00..\x1F\\\"'"))
					. "</tt><br />\n";
			}

			$BBCode_Profiler->begin('Lexer:Split');

			// The lexer is responsible for converting individual characters to tokens,
			// and uses preg_split to do most of its magic.  Because it uses preg_split
			// and not a character-by-character tokenizer, the structure of the input
			// must be known in advance, which is why the tag marker cannot be changed
			// during the parse.
			$this->lexer = new BBCodeLexer($string, $this->tag_marker);
			$this->lexer->debug = $this->debug;

			$BBCode_Profiler->end('Lexer:Split');

			// If we're fuzzily limiting the text length, see if we need to actually
			// cut it off, or if it's close enough to not be worth the effort.
			$old_output_limit = $this->output_limit;
			if ($this->output_limit > 0) {
				if ($this->debug)
					print "<b>Parse:</b> Limiting text length to {$this->output_limit}.<br />\n";
				if (strlen($string) < $this->output_limit) {
					// Easy case:  A short string can't possibly be longer than the output
					// limit, so just turn off the output limit.
					$this->output_limit = 0;
					if ($this->debug)
						print "<b>Parse:</b> Not bothering to limit:  Text is too short already.<br />\n";
				}
				else if ($this->limit_precision > 0) {
					// We're using fuzzy precision, so make a guess as to how long the text is,
					// and then decide whether we can let this string slide through based on the
					// limit precision.
					$guess_length = $this->lexer->guessTextLength();
					if ($this->debug)
						print "<b>Parse:</b> Maybe not:  Fuzzy limiting enabled, and approximate text length is $guess_length.<br />\n";
					if ($guess_length < $this->output_limit * ($this->limit_precision + 1.0)) {
						if ($this->debug)
							print "<b>Parse:</b> Not limiting text; it's close enough to the limit to be acceptable.<br />\n";
						$this->output_limit = 0;
					}
					else {
						if ($this->debug)
							print "<b>Parse:</b> Limiting text; it's definitely too long.<br />\n";
					}
				}
			}

			// The token stack is used to perform a document-tree walk without actually
			// building the document tree, and is an essential component of our input-
			// validation algorithm.
			$this->stack = [];

			// There are no start tags (yet).
			$this->start_tags = [];

			// There are no unmatched start tags (yet).
			$this->lost_start_tags = [];

			// There is no text yet.
			$this->text_length = 0;
			$this->was_limited = false;

			// Remove any initial whitespace in pre-trim mode.
			if (strlen($this->pre_trim) > 0)
				$this->internal_CleanupWSByEatingInput($this->pre_trim);

			// In plain mode, we generate newlines instead of <br /> tags.
			$newline = $this->plain_mode ? "\n" : "<br />\n";

			// This is a fairly straightforward push-down automaton operating in lL(1) mode.  For
			// clarity's sake, we break the tag-processing code into separate functions, but we
			// keep the text/whitespace/newline code here for performance reasons.
			while (true) {
				$BBCode_Profiler->begin('Lexer:NextToken');
				if (($token_type = $this->lexer->nextToken()) == BBCODE_EOI) {
					$BBCode_Profiler->end('Lexer:NextToken');
					break;
				}

				if ($this->debug)
					print "<b>Parse:</b> Stack contents: <tt>" . $this->internal_DumpStack() . "</tt><br />\n";

				switch ($token_type) {

				case BBCODE_TEXT:
					// Text is like an arithmetic operand, so just push it onto the stack because we
					// won't know what to do with it until we reach an operator (e.g., a tag or EOI).
					if ($this->debug) {
						print "<hr />\n<b>Internal_ParseTextToken:</b> fixup and push text: <tt>"
							. htmlspecialchars($this->lexer->text) . "</tt><br />\n";
					}

					// If this token pushes us past the output limit, split it up on a whitespace
					// boundary, add as much as we can, and then abort.
					if ($this->output_limit > 0
						&& $this->text_length + strlen($this->lexer->text) >= $this->output_limit) {
						$text = $this->internal_LimitText($this->lexer->text,
							$this->output_limit - $this->text_length);
						if (strlen($text) > 0) {
							$this->text_length += strlen($text);
							$this->stack[] = [
								BBCODE_STACK_TOKEN => BBCODE_TEXT,
								BBCODE_STACK_TEXT => $this->fixupOutput($text),
								BBCODE_STACK_TAG => false,
								BBCODE_STACK_CLASS => $this->current_class,
							];
						}
						$this->internal_DoLimit();
						break 2;
					}
					$this->text_length += strlen($this->lexer->text);

					// Push this text token onto the stack.
					$this->stack[] = [
						BBCODE_STACK_TOKEN => BBCODE_TEXT,
						BBCODE_STACK_TEXT => $this->fixupOutput($this->lexer->text),
						BBCODE_STACK_TAG => false,
						BBCODE_STACK_CLASS => $this->current_class,
					];
					break;

				case BBCODE_WS:
					// Whitespace is like an operand too, so just push it onto the stack, but
					// sanitize it by removing all non-tab non-space characters.
					if ($this->debug)
						print "<hr />\n<b>Internal_ParseWhitespaceToken:</b> fixup and push whitespace<br />\n";

					// If this token pushes us past the output limit, don't process anything further.
					if ($this->output_limit > 0
						&& $this->text_length + strlen($this->lexer->text) >= $this->output_limit) {
						$this->internal_DoLimit();
						break 2;
					}
					$this->text_length += strlen($this->lexer->text);

					// Push this whitespace onto the stack.
					$this->stack[] = [
						BBCODE_STACK_TOKEN => BBCODE_WS,
						BBCODE_STACK_TEXT => $this->lexer->text,
						BBCODE_STACK_TAG => false,
						BBCODE_STACK_CLASS => $this->current_class,
					];
					break;

				case BBCODE_NL:
					// Newlines are really like tags in disguise:  They insert a replaced
					// element into the output, and are actually more-or-less like plain text.

					if ($this->debug)
						print "<hr />\n<b>Internal_ParseNewlineToken:</b> got a newline.<br />\n";

					if ($this->ignore_newlines) {
						if ($this->debug)
							print "<b>Internal_ParseNewlineToken:</b> push newline as whitespace.<br />\n";

						// If this token pushes us past the output limit, don't process anything further.
						if ($this->output_limit > 0
							&& $this->text_length + 1 >= $this->output_limit) {
							$this->internal_DoLimit();
							break 2;
						}
						$this->text_length += 1;

						// In $ignore_newlines mode, we simply push the newline as whitespace.
						// Note that this can yield output that's slightly different than the
						// input:  For example, a "\r\n" input will produce a "\n" output; but
						// this should still be acceptable, since we're working with text, not
						// binary data.
						$this->stack[] = [
							BBCODE_STACK_TOKEN => BBCODE_WS,
							BBCODE_STACK_TEXT => "\n",
							BBCODE_STACK_TAG => false,
							BBCODE_STACK_CLASS => $this->current_class,
						];
					}
					else {
						// Any whitespace before a newline isn't worth outputting, so if there's
						// whitespace sitting on top of the stack, remove it so that it doesn't
						// get outputted.
						$this->internal_CleanupWSByPoppingStack("s", $this->stack);

						if ($this->debug)
							print "<b>Internal_ParseNewlineToken:</b> push newline.<br />\n";

						// If this token pushes us past the output limit, don't process anything further.
						if ($this->output_limit > 0
							&& $this->text_length + 1 >= $this->output_limit) {
							$this->internal_DoLimit();
							break 2;
						}
						$this->text_length += 1;

						// Add the newline to the stack.
						$this->stack[] = [
							BBCODE_STACK_TOKEN => BBCODE_NL,
							BBCODE_STACK_TEXT => $newline,
							BBCODE_STACK_TAG => false,
							BBCODE_STACK_CLASS => $this->current_class,
						];

						// Any whitespace after a newline is meaningless, so if there's whitespace
						// lingering on the input after this, remove it now.
						$this->internal_CleanupWSByEatingInput("s");
					}
					break;

				case BBCODE_TAG:
					// Use a separate function to handle tags, because they're complicated.
					$this->internal_ParseStartTagToken();
					break;

				case BBCODE_ENDTAG:
					// Use a separate function to handle end tags, because they're complicated.
					$this->internal_ParseEndTagToken();
					break;

				default:
					break;
				}
			}

			if ($this->debug)
				print "<hr />\n<b>Parse Done:</b> done main parse; packing stack as text string.<br />\n";

			// Remove any trailing whitespace in post-trim mode.
			if (strlen($this->post_trim) > 0)
				$this->internal_CleanupWSByPoppingStack($this->post_trim, $this->stack);

			// Everything left on the stack should be HTML (or broken tags), so pop it
			// all off as plain text, concatenate it, and return it.
			$result = $this->internal_GenerateOutput(0);
			$result = $this->internal_CollectTextReverse($result, count($result) - 1);

			// If we changed the limit (in fuzzy-limit mode), set it back.
			$this->output_limit = $old_output_limit;

			// In plain mode, we do just a *little* more cleanup on the whitespace to shorten
			// the output as much as possible.
			if ($this->plain_mode) {
				// Turn all non-newline whitespace characters into single spaces.
				$result = preg_replace("/[\\x00-\\x09\\x0B-\\x20]+/", " ", $result);
				// Turn multiple newlines into at most two newlines.
				$result = preg_replace("/(?:[\\x20]*\\n){2,}[\\x20]*/", "\n\n", $result);
				// Strip off all surrounding whitespace.
				$result = trim($result);
			}

			if ($this->debug) {
				print "<b>Parse:</b> return: <tt>" . htmlspecialchars(addcslashes($result, "\x00..\x1F\\\"'"))
					. "</tt><br />\n";
			}

			$BBCode_Profiler->end('_Parse');

			//$BBCode_Profiler->dumpAllGroups();

			return $result;
		}
	}

?>
