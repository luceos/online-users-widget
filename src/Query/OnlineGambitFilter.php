<?php

/*
 * This file is part of afrux/online-users-widget.
 *
 * Copyright (c) 2021 Sami Mazouz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Afrux\OnlineUsers\Query;

use Carbon\Carbon;
use Flarum\Filter\FilterInterface;
use Flarum\Filter\FilterState;
use Flarum\Search\AbstractRegexGambit;
use Flarum\Search\SearchState;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Query\Builder;

class OnlineGambitFilter extends AbstractRegexGambit implements FilterInterface
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(SearchState $search, $bit)
    {
        if (! $search->getActor()->hasPermission('user.viewLastSeenAt')) {
            return false;
        }

        return parent::apply($search, $bit);
    }

    /**
     * {@inheritdoc}
     */
    public function getGambitPattern()
    {
        return 'is:online';
    }

    /**
     * {@inheritdoc}
     */
    protected function conditions(SearchState $search, array $matches, $negate)
    {
        $this->constrain($search->getQuery(), $matches[1], $negate);
    }

    public function getFilterKey(): string
    {
        return 'online';
    }

    public function filter(FilterState $filterState, string $filterValue, bool $negate)
    {
        if (! $filterState->getActor()->hasPermission('user.viewLastSeenAt')) {
            return;
        }

        $this->constrain($filterState->getQuery(), $negate);
    }

    protected function constrain(Builder $query, ?bool $negate = false)
    {
        $time = Carbon::now()->subMinutes(5);
        $limit = $this->settings->get('afrux-online-users-widget.max_users', 15);

        // @TODO this is a temporary solution because the preferences are stored in a binary,
        // so we can't access individual users's discloseOnline preference, this should be perfected
        // when flarum core improves the way it stores preferences.
        $lastSeenUsers = User::query()
            ->select('id', 'preferences')
            ->where('last_seen_at', $negate ? '<=' : '>', $time)
            ->limit($limit + 1)
            ->get()
            ->filter(function ($user) {
                return (bool) $user->getPreference('discloseOnline');
            })
            ->pluck('id');

        $query->whereIn('id', $lastSeenUsers->toArray());
    }
}
