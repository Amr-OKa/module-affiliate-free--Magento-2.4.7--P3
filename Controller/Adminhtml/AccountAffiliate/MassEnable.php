<?php 

namespace Lof\Affiliate\Controller\Adminhtml\AccountAffiliate;

use Magento\Backend\App\Action;
use Magento\Framework\Exception\LocalizedException;
use Lof\Affiliate\Model\AccountAffiliateFactory;

class MassEnable extends Action
{
    protected $accountAffiliateFactory;

    public function __construct(
        Action\Context $context,
        AccountAffiliateFactory $accountAffiliateFactory
    ) {
        parent::__construct($context);
        $this->accountAffiliateFactory = $accountAffiliateFactory;
    }

    /**
     * Execute mass enable action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // Get selected item IDs from the request
        $ids = $this->getRequest()->getParam('selected', []);

        // Check if no items were selected
        if (empty($ids)) {
            // Throw an exception with a user-friendly message
            throw new LocalizedException(__('An item needs to be selected. Select and try again.'));
        }

        // Proceed with enabling the selected items
        $model = $this->accountAffiliateFactory->create();

        foreach ($ids as $id) {
            $model->load($id);
            if ($model->getId()) {
                $model->setIsEnabled(true); // Set the item as enabled
                $model->save(); // Save the changes
            }
        }

        // Add success message
        $this->messageManager->addSuccess(__('The selected items have been enabled.'));

        // Redirect back to the grid
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
