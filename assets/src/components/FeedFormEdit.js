/**
 * Stand-alone edit page wrapper
 */
import FeedFormShared from './FeedFormShared';

export default function FeedFormEdit({ feedId }) {
    return <FeedFormShared feedId={feedId} onSuccess={() => window.location = 'admin.php?page=sc-autoparser'} />;
}
