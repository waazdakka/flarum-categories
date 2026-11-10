<?php

/*
 * This file is part of fof/categories
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\Categories;

use Flarum\Discussion\Discussion;
use Flarum\Tags\Tag;
use Flarum\User\User;

class Unread
{
    /** @var array<int, int[]> memoised per actor for the current request */
    protected static array $cache = [];

    /**
     * IDs of tags (including parents of tags) containing at least one
     * discussion visible to $actor that has unread posts for them.
     *
     * @return int[]
     */
    public static function tagIdsFor(User $actor): array
    {
        if (isset(static::$cache[$actor->id])) {
            return static::$cache[$actor->id];
        }

        $ids = Discussion::query()
            ->join('discussion_tag', 'discussions.id', '=', 'discussion_tag.discussion_id')
            ->leftJoin('discussion_user', function ($join) use ($actor) {
                $join->on('discussion_user.discussion_id', '=', 'discussions.id')
                    ->where('discussion_user.user_id', '=', $actor->id);
            })
            ->whereVisibleTo($actor)
            ->whereNull('discussions.hidden_at')
            ->where('discussions.last_post_number', '>', 0)
            ->where(function ($query) {
                $query->whereNull('discussion_user.last_read_post_number')
                    ->orWhereColumn('discussion_user.last_read_post_number', '<', 'discussions.last_post_number');
            })
            ->distinct()
            ->pluck('discussion_tag.tag_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids) {
            $parents = Tag::query()->whereIn('id', $ids)->whereNotNull('parent_id')->pluck('parent_id')->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_unique(array_merge($ids, $parents)));
        }

        return static::$cache[$actor->id] = $ids;
    }
}
