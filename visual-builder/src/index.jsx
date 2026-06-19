import React from 'react';

const { addAction } = window?.vendor?.wp?.hooks;

const {
  ModuleContainer,
  StyleContainer,
  elementClassnames,
} = window?.divi?.module;

const { registerModule } = window?.divi?.moduleLibrary;

import metadata from './module.json';

const ModuleStyles = ({ attrs, elements, settings, orderClass, mode, state, noStyleTag }) => (
  <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
    {elements.style({
      attrName: 'module',
      styleProps: {
        disabledOn: {
          disabledModuleVisibility: settings?.disabledModuleVisibility,
        },
      },
    })}
  </StyleContainer>
);

const ModuleScriptData = ({ elements }) => (
  <React.Fragment>
    {elements.scriptData({ attrName: 'module' })}
  </React.Fragment>
);

const moduleClassnames = ({ classnamesInstance, attrs }) => {
  classnamesInstance.add(
    elementClassnames({
      attrs: attrs?.module?.decoration ?? {},
    }),
  );
};

const wcmsProductFormModule = {
  metadata,
  renderers: {
    edit: ({ attrs, id, name, elements }) => {
      const productId = attrs?.module?.advanced?.product_id?.desktop?.value || '0';
      const isCurrentProduct = productId === '0';

      return (
        <ModuleContainer
          attrs={attrs}
          elements={elements}
          id={id}
          moduleClassName="wcms_product_form"
          name={name}
          scriptDataComponent={ModuleScriptData}
          stylesComponent={ModuleStyles}
          classnamesFunction={moduleClassnames}
        >
          {elements.styleComponents({ attrName: 'module' })}
          <div className="et_pb_module_inner">
            <div className="wcms-divi-placeholder">
              <div className="wcms-divi-placeholder-title">WCMS Product Calculator</div>
              <div className="wcms-divi-placeholder-desc">
                {isCurrentProduct
                  ? 'Renders the meter calculator for the current product on the frontend.'
                  : `Product ID: ${productId} (shown on frontend)`}
              </div>
            </div>
          </div>
        </ModuleContainer>
      );
    },
  },
  placeholderContent: {
    module: {
      meta: {
        adminLabel: {
          desktop: {
            value: 'WCMS Product Calculator',
          },
        },
      },
    },
  },
};

addAction(
  'divi.moduleLibrary.registerModuleLibraryStore.after',
  'wcms-divi-integration',
  () => {
    registerModule(wcmsProductFormModule.metadata, wcmsProductFormModule);
  }
);
