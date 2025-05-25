<?php

namespace SegWeb\Http\Controllers;
use Auth;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Storage;
use SegWeb\File;
use SegWeb\FileResults;
use SegWeb\Http\Controllers\Tools;
use SegWeb\Http\Controllers\FileController;
use SegWeb\Http\Controllers\FileResultsController;

class GithubFilesController extends Controller
{
    private $github_files_ids = NULL;

    public function index()
    {
        return view('github');
    }

    public function downloadGithub(Request $request)
    {
        $githubLink = rtrim($request->github_link, '/');

        if (!Tools::contains('github', $githubLink)) {
            return $this->handleError('An invalid repository link has been submitted!', $request);
        }

        try {
            $userId = Auth::check() ? Auth::id() : 0;

            $branch = $request->branch;
            $url = "{$githubLink}/archive/{$branch}.zip";

            $folder = 'github_uploads/';
            $timestamp = now()->format('ymdhis');
            $filename = "{$folder}{$timestamp}_" . basename($url);

            $zipContent = @file_get_contents($url);
            if ($zipContent === false || !Storage::put($filename, $zipContent)) {
                return $this->handleError('An error occurred during repository download.', $request);
            }

            // Unzip and cleanup
            $unzippedPath = base_path("storage/app/{$folder}{$timestamp}_{$branch}");
            unlink(storage_path("app/{$filename}"));

            // Save file info
            $projectParts = explode('/', $githubLink);
            $projectName = end($projectParts);

            $file = File::create([
                'user_id' => $userId,
                'file_path' => "{$folder}{$timestamp}_{$branch}",
                'original_file_name' => $projectName,
                'type' => 'Github Repository',
            ]);

            // Analyze files
            $this->analiseGithubFiles($unzippedPath, $file->id);

            // Gather file results
            $fileContents = [];
            if (!empty($this->github_files_ids)) {
                $fileResultsController = new FileResultsController();

                foreach ($this->github_files_ids as $id) {
                    $fileContents[$id] = [
                        'content' => FileController::getFileContentArray($id),
                        'results' => $fileResultsController->getSingleByFileId($id),
                        'file' => FileController::getFileById($id),
                    ];
                }
            }

            $successMsg = ['text' => 'Repository has been successfully downloaded!', 'type' => 'success'];

            if ($request->path() === 'github') {
                return view('github', compact('file', 'fileContents', 'successMsg'));
            }

            return response()->json($this->getResultArray($file, $fileContents));
        } catch (\Exception $e) {
            return $this->handleError('An error occurred while processing the request.', $request);
        }
    }

    private function handleError(string $message, Request $request)
    {
        $msg = ['text' => $message, 'type' => 'error'];

        if ($request->path() === 'github') {
            return view('github', compact('msg'));
        }

        return response()->json(['error' => $msg['text']], 500);
    }


    public function analiseGithubFiles($dir, $repositoryId)
    {
        $files = $this->getDirectoryContents($dir);

        if (empty($files)) {
            return;
        }

        $terms = (new TermController())->getTerm();

        foreach ($files as $fileName) {
            $fullPath = "{$dir}/{$fileName}";

            if (is_dir($fullPath)) {
                $this->analiseGithubFiles($fullPath, $repositoryId);
                continue;
            }

            if (!$this->isPhpFile($fullPath)) {
                continue;
            }

            $fileModel = $this->storeGithubFile($fullPath, $fileName, $repositoryId);
            $this->analyzeFileContents($fullPath, $fileModel->id, $terms);
        }
    }

    private function getDirectoryContents($dir): array
    {
        $items = scandir($dir);
        return array_values(array_diff($items, ['.', '..']));
    }

    private function isPhpFile($path): bool
    {
        $mime = mime_content_type($path);
        return in_array($mime, ['text/x-php', 'application/x-php']);
    }

    private function storeGithubFile($fullPath, $fileName, $repositoryId): File
    {
        $filePath = explode('storage/app/', $fullPath)[1];

        $file = new File();
        $file->user_id = Auth::id() ?? 0;
        $file->file_path = $filePath;
        $file->original_file_name = $fileName;
        $file->type = 'Github File';
        $file->repository_id = $repositoryId;
        $file->save();

        $this->github_files_ids[] = $file->id;

        return $file;
    }

    private function analyzeFileContents($path, $fileId, $terms): void
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $lineNumber = 1;
        while (($line = fgets($handle)) !== false) {
            foreach ($terms as $term) {
                if (Tools::contains($term->term, $line)) {
                    FileResults::create([
                        'file_id' => $fileId,
                        'line_number' => $lineNumber,
                        'term_id' => $term->id,
                    ]);
                }
            }
            $lineNumber++;
        }

        fclose($handle);
    }


    public function getResultArray($file, $file_contents)
    {
        $array = [];
        foreach ($file_contents as $value) {
            $file_results = $value['results'];
            $file_path = explode('/', explode($file->original_file_name, $value['file']->file_path)[1]);
            unset($file_path[0]);
            $file_path = $file->original_file_name . '/' . implode('/', $file_path);

            $array[] = ['file' => $file_path];

            foreach ($file_results as $results) {
                $array['problems'][] = [
                    'line' => $results->line_number,
                    'category' => $results->term_type,
                    'problem' => $results->term
                ];
            }
        }
        return $array;
    }
}
