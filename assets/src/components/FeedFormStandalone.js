/**
 * Add-new page wrapper
 */
import FeedFormShared from './FeedFormShared';

export default function FeedFormStandalone() {
    return <FeedFormShared onSuccess={() => window.location.reload()} />;
}
