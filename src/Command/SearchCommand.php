<?php

namespace Osky;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;
use GuzzleHttp\Client;

class SearchCommand extends Command
{
    protected function configure()
    {
        $this->setName('reddit:search')
            ->setDescription('Search for new posts on a specified subreddit');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Prompt user for subreddit and search term
        $subreddit = $this->getHelper('question')->ask($input, $output, new Question("Enter subreddit (default: webdev): "));
        $subreddit = $subreddit ?: 'webdev';
        $searchTerm = $this->getHelper('question')->ask($input, $output, new Question("Enter search term (default: php): "));
        $searchTerm = $searchTerm ?: 'php';
        $subreddit = strtolower($subreddit);
        $searchTerm = strtolower($searchTerm);

        // Use Guzzle HTTP client to communicate with the Reddit API
        $client = new Client();
        try {
            $response = $client->get("https://www.reddit.com/r/$subreddit/new.json?limit=100");
            $data = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $output->writeln("Error occured while trying to retrieve data from API: " . $e->getMessage());
            return;
        }

        // Filter and sort posts
        $posts = array_filter(array_map(function ($post) use ($searchTerm) {
            $selftext = $post['data']['selftext'];
            if (empty($selftext)) {
                return false;
            }
            if (strpos(strtolower($selftext), $searchTerm) === false && strpos(strtolower($post['data']['title']), $searchTerm) === false) {
                return false;
            }
            return [
                'date' => date("Y-m-d H:i:s", $post['data']['created_utc'] + (8 * 60 * 60)),
                'title' => $post['data']['title'],
                'url' => $post['data']['url'],
                'excerpt' => $this->createExcerpt($selftext, $searchTerm)
            ];
        }, $data['data']['children']), function ($post) {
            return $post !== false;
        });

        if (empty($posts)) {
            $output->writeln("No posts found.");
            return;
        }

        usort($posts, function ($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
        // Display results in table style
        $table = new Table($output);
        $table->setHeaders(['Date', 'Title', 'URL', 'Excerpt'])
            ->setRows(array_map(function ($post) {
                return [
                    $post['date'],
                    substr($post['title'], 0, 30),
                    $post['url'],
                    $post['excerpt']
                ];
            }, $posts));
        $table->render();
        return 0;
    }

    private function createExcerpt($selftext, $searchTerm)
    {
        $excerpt = '';
        $searchTermIndex = strpos(strtolower($selftext), $searchTerm);
        if ($searchTermIndex !== false) {
            $start = max(0, $searchTermIndex - 20);
            $end = min(strlen($selftext), $searchTermIndex + 20);
            $excerpt = substr($selftext, $start, $end - $start);
            if ($start != 0) {
                $excerpt = '...' . $excerpt;
            }
            if ($end != strlen($selftext)) {
                $excerpt .= '...';
            }
            $excerpt = str_replace($searchTerm, "\033[1;4m".$searchTerm."\033[0m", $excerpt);
        }
        return $excerpt;
    }
}
