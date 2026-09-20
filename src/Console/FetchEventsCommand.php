<?php

namespace redundans\Calendarevent\Console;

use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use Flarum\User\User;
use Flarum\Tags\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use GuzzleHttp\Client;

class FetchEventsCommand extends Command
{
	protected $signature = 'noden:fetch-events';
	protected $description = 'Hämtar objekt från JSON-fil och skapar forumtrådar.';

	public function handle()
	{
		$this->info('Startar hämtning av JSON...');

		$jsonUrl = 'https://www.gnistor.se/feed.json';
		$actor = User::find(25);

		if (!$actor) {
			$this->error('Kunde inte hitta användaren med ID 23.');
			return;
		}

		$client = new Client();
		try {
			$response = $client->get($jsonUrl);
            $data = json_decode($response->getBody()->getContents(), true);
            $items = Arr::get($data, 'items', []);
		} catch (\Throwable $e) {
			$this->error('Kunde inte hämta JSON-filen: ' . $e->getMessage());
			return;
		}

		if (empty($items)) {
			$this->info('Inga objekt hittades i JSON-filen.');
			return;
		}

		foreach ($items as $item) {
			$title = Arr::get($item, 'title');
			$rawContent = Arr::get($item, 'description');
			$linkposter_url = Arr::get($item, 'link');
			$content = html_entity_decode($rawContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $locations = (array) Arr::get($item, 'locations', []);
            $startString = Arr::get($item, 'start_time');
            $startDate = $startString ? new \DateTime($startString) : new \DateTime(); // ska in på calendarEventDate


			$exists = Discussion::where('linkposter_url', $linkposter_url)->exists();

			if ($exists) {
				$this->line("Objektet med linkposter_url ${$linkposter_url} finns redan. Hoppar över.");
				continue;
			}

			try {
				$this->info("Hittade nytt event. Skapar tråd: \"{$title}\"...");

				$discussion = Discussion::start($title, $actor, null);
                $discussion->linkposter_description = $content;
                $discussion->linkposter_url = Arr::get($item, 'link');
                $discussion->created_at = new \DateTime();
                $discussion->calendar_event_date = $startDate->format('Y-m-d H:i:s');;
				$discussion->save();

				$content = strip_tags($content);

				$post = new CommentPost();
				$post->discussion_id = $discussion->id;

				$post->content    = '';
				$post->user_id    = $actor->id;
				$post->ip_address = '127.0.0.1';
				$post->created_at = \Carbon\Carbon::now();
				$post->type       = 'comment';
                $post->created_at = new \DateTime();
				$post->save();

				$discussion->refreshCommentCount();
                $discussion->setFirstPost($post);
				$discussion->refreshLastPost();
				$discussion->save();

				try {
					$db = app('flarum.db');

                    $tagNames = array_merge(['Kalender'], (array) $locations);
                    $tagIds = $db->table('tags')
                        ->whereIn('name', $tagNames)
                        ->pluck('id')
                        ->toArray();

                    if (!empty($tagIds)) {
                        // Ta bort gamla tagg-kopplingar för diskussionen om det behövs
                        $db->table('discussion_tag')
                            ->where('discussion_id', $discussion->id)
                            ->delete();

                        // Förbered rader för insättning
                        $insertData = [];
                        foreach ($tagIds as $tagId) {
                            $insertData[] = [
                                'discussion_id' => $discussion->id,
                                'tag_id'        => $tagId
                            ];
                        }

                        // Sätt in alla nya tagg-kopplingar på en gång
                        $db->table('discussion_tag')->insert($insertData);

                        // Skapa en sträng av ID-numren för loggningen
                        $tagIdsString = implode(', ', $tagIds);
                        $this->info("Kopplade tråden till taggarna (ID: {$tagIdsString}) direkt i databasen.");
                    }
				} catch (\Throwable $tagError) {
					$this->warn("Kunde inte synka tagg via databasen: " . $tagError->getMessage());
				}

				$this->info("Klart! Skapade tråd för {$title}.");
			} catch (\Throwable $dbError) {
				$this->error("Kraschade vid skapande av tråd: " . $dbError->getMessage());
				return;
			}
		}

        $this->info('JSON-synkronisering slutförd!');
        return Command::SUCCESS;
	}
}
