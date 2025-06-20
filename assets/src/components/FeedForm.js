import FeedFormShared from './FeedFormShared';

export default function FeedForm() {
    const feedId = useSelect(sel => sel(STORE_KEY).getFormFeed()?.id);
    const {closeForm} = useDispatch(STORE_KEY);
    return (
        <FeedFormShared
            feedId={feedId}
            onSuccess={closeForm}
        />
    );
}
