import React from 'react';
import { HashRouter as Router, Route, Switch } from 'react-router-dom';
import Settings from '../Pages/settings';
import GenerateDoc from '../Pages/generatedoc';

const routes = [
  {
    path: '/settings',
    component: Settings,
  },
  {
    path: '/generate-doc',
    component: GenerateDoc,
  },
];

/**
 * Render all routes
 */
function Routerview() {
  return (
    <>
      <Router>
        <Switch>
          {routes.map((route, i) => (
            <RenderRoute key={i} {...route} />
          ))}
        </Switch>
      </Router>
    </>
  );
}

/**
 * Render route component matching with path
 *
 * @param {*} route
 */
function RenderRoute(route) {
  return <Route path={route.path} component={route.component} />;
}

export default Routerview;
