<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


namespace Widgets\ActionLog\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CRoleHelper;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		[$sortfield, $sortorder] = self::getSorting($this->fields_values['sort_triggers']);

		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'has_access' => [
				CRoleHelper::UI_REPORTS_ACTION_LOG => $this->checkAccess(CRoleHelper::UI_REPORTS_ACTION_LOG)
			],
			'statuses' => $this->fields_values['statuses'],
			'message' => $this->fields_values['message'],
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$search_strings = [];

		if ($data['message']) {
			$search_strings = explode(' ', $data['message']);
		}

		$data['alerts'] = $this->getAlerts(
			$data['statuses'], $search_strings, $sortfield, $sortorder, $this->fields_values['show_lines']
		);

		$data['db_users'] = $this->getDbUsers($data['alerts']);

		$data['actions'] = API::Action()->get([
			'output' => ['actionid', 'name'],
			'actionids' => array_unique(array_column($data['alerts'], 'actionid')),
			'preservekeys' => true
		]);

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getAlerts(array $filter_statuses, array $search_strings, string $sortfield, string $sortorder,
			$show_lines): array {
		$alerts = API::Alert()->get([
			'output' => ['clock', 'sendto', 'subject', 'message', 'status', 'retries', 'error', 'userid', 'actionid',
				'mediatypeid', 'alerttype'
			],
			'selectMediatypes' => ['name', 'maxattempts'],
			'filter' => ['status' => $filter_statuses],
			'search' => [
				'subject' => $search_strings,
				'message' => $search_strings
			],
			'searchByAny' => true,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => $show_lines
		]);

		foreach ($alerts as &$alert) {
			$alert['description'] = '';

			if ($alert['mediatypeid'] != 0 && array_key_exists(0, $alert['mediatypes'])) {
				$alert['description'] = $alert['mediatypes'][0]['name'];
				$alert['maxattempts'] = $alert['mediatypes'][0]['maxattempts'];
			}
			unset($alert['mediatypes']);

			$alert['action_type'] = ZBX_EVENT_HISTORY_ALERT;
		}
		unset($alert);

		return $alerts;
	}

	private function getDbUsers(array $alerts): array {
		$userids = [];

		foreach ($alerts as $alert) {
			$userids[$alert['userid']] = true;
		}
		unset($userids[0]);

		return $userids
			? API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_keys($userids),
				'preservekeys' => true
			])
			: [];
	}

	private static function getSorting(int $sort_triggers): array {
		switch ($sort_triggers) {
			case SCREEN_SORT_TRIGGERS_TIME_ASC:
				return ['clock', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_TIME_DESC:
			default:
				return ['clock', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_MEDIA_TYPE_ASC:
				return ['mediatypeid', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_MEDIA_TYPE_DESC:
				return ['mediatypeid', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_STATUS_ASC:
				return ['status', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_STATUS_DESC:
				return ['status', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_RECIPIENT_ASC:
				return ['sendto', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_RECIPIENT_DESC:
				return ['sendto', ZBX_SORT_DOWN];
		}
	}

}
