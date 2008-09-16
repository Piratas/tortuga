<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once('XMPPHP/XMPP.php');

/* Subscribe $user to nickname $other_nickname
  Returns true or an error message.
*/

function subs_subscribe_user($user,$other_nickname) {

	$other = User::staticGet('nickname', $other_nickname);

	if (!$other) {
		return _('No such user.');
	}

	return subs_subscribe_to($user, $other);
}

function subs_subscribe_to($user, $other) {

	if ($user->isSubscribed($other)) {
		return _('Already subscribed!.');
	}

	if (!$user->subscribeTo($other)) {
		return _('Could not subscribe.');
		return;
	}

	subs_notify($other, $user);

	if (common_config('memcached', 'enabled')) {
		$cache = new Memcache();
		if ($cache->connect(common_config('memcached', 'server'), common_config('memcached', 'port'))) {
			$cache->delete(common_cache_key('user:notices_with_friends:' . $user->id));
		}
	}
	
	if ($other->autosubscribe && !$other->isSubscribed($user)) {
		if (!$other->subscribeTo($user)) {
			return _('Could not subscribe other to you.');
		}
		if (common_config('memcached', 'enabled')) {
			$cache = new Memcache();
			if ($cache->connect(common_config('memcached', 'server'), common_config('memcached', 'port'))) {
				$cache->delete(common_cache_key('user:notices_with_friends:' . $other->id));
			}
		}
		
		subs_notify($user, $other);
	}

	return true;
}

function subs_notify($listenee, $listener) {
	# XXX: add other notifications (Jabber, SMS) here
	# XXX: queue this and handle it offline
	# XXX: Whatever happens, do it in Twitter-like API, too
	subs_notify_email($listenee, $listener);
}

function subs_notify_email($listenee, $listener) {
	mail_subscribe_notify($listenee, $listener);
}

/* Unsubscribe $user from nickname $other_nickname
  Returns true or an error message.
*/
function subs_unsubscribe_user($user, $other_nickname) {

	$other = User::staticGet('nickname', $other_nickname);

	if (!$other) {
		return _('No such user.');
	}

	return subs_unsubscribe_to($user, $other);
}

function subs_unsubscribe_to($user, $other) {

	if (!$user->isSubscribed($other))
		return _('Not subscribed!.');

	$sub = DB_DataObject::factory('subscription');

	$sub->subscriber = $user->id;
	$sub->subscribed = $other->id;

	$sub->find(true);

	// note we checked for existence above

	if (!$sub->delete())
		return _('Couldn\'t delete subscription.');

	if (common_config('memcached', 'enabled')) {
		$cache = new Memcache();
		if ($cache->connect(common_config('memcached', 'server'), common_config('memcached', 'port'))) {
			$cache->delete(common_cache_key('user:notices_with_friends:' . $user->id));
		}
	}
	
	return true;
}

