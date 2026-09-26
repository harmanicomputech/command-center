<x-select name="topic" label="Topic" :id="$prefix.'-topic'" :options="config('messaging.policy_topics')" :value="$document?->topic" />
<x-input name="title" label="Title" :id="$prefix.'-title'" :value="$document?->title" placeholder="e.g. Fertiliser and mechanisation for smallholders" required />
<x-textarea name="body" label="The position" :id="$prefix.'-body'" :value="$document?->body" rows="8" placeholder="What the candidate will do, where, and the first steps. Facts the campaign stands behind." required />
<x-checkbox name="active" :id="$prefix.'-active'" label="Use in AI drafts" :checked="$document?->active ?? true" switch />
